<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Biblioteca de demonstração compartilhada.
 *
 * Os arquivos ficam na pasta audios/ do projeto (fora da pasta pública) e pertencem a uma
 * "rádio de sistema" (slug __demo), que não tem usuários nem aparece no painel. Todas as
 * rádios podem LER e TOCAR esses áudios; ninguém consegue editar, excluir ou enviar para eles.
 * Os arquivos enviados por cada rádio continuam visíveis só para ela.
 */
final class DemoLibrary
{
    public const SLUG = '__demo';
    private const PREFER = ['mp3' => 0, 'ogg' => 1, 'opus' => 1, 'm4a' => 2, 'aac' => 2, 'webm' => 3, 'flac' => 4, 'oga' => 1, 'wav' => 5];

    private static ?int $id = null;

    public static function dir(): string
    {
        return rtrim((string) env('DEMO_AUDIO_DIR', BASE_PATH . '/audios'), '/\\');
    }

    /** Id da rádio de sistema dona dos áudios de demonstração (0 se não houver pasta audios/). */
    public static function id(): int
    {
        if (self::$id !== null) {
            return self::$id;
        }
        $id = (int) DB::value('SELECT id FROM stations WHERE slug = ?', [self::SLUG]);
        if (!$id && is_dir(self::dir())) {
            $plan = (int) DB::value('SELECT MIN(id) FROM plans');
            $id = DB::insert('stations', [
                'slug' => self::SLUG, 'name' => 'Biblioteca de demonstração', 'plan_id' => $plan, 'status' => 'system',
                'timezone' => 'America/Sao_Paulo', 'settings' => '{}', 'public_token' => bin2hex(random_bytes(12)),
            ]);
        }
        return self::$id = $id;
    }

    /** Rádios cujos áudios a rádio $stationId pode ler: ela mesma + a demonstração. */
    public static function readable(int $stationId): array
    {
        $demo = self::id();
        return $demo && $demo !== $stationId ? [$stationId, $demo] : [$stationId];
    }

    public static function isDemo(int $stationId): bool
    {
        return $stationId === self::id() && $stationId !== 0;
    }

    /** Caminho seguro do arquivo de demonstração (nunca sai da pasta audios/). */
    public static function path(string $relative): ?string
    {
        $base = realpath(self::dir());
        $full = $base ? realpath($base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative)) : false;
        if (!$base || !$full || !str_starts_with($full, $base . DIRECTORY_SEPARATOR) || !is_file($full)) {
            return null;
        }
        return $full;
    }

    /**
     * Sincroniza a pasta audios/ com o banco. Só faz o trabalho pesado quando alguma pasta mudou
     * (arquivo novo, apagado ou renomeado), então pode ser chamado em toda abertura da biblioteca.
     */
    public static function sync(bool $force = false): ?array
    {
        $root = self::dir();
        if (!is_dir($root)) {
            return null;
        }
        $dirs = [$root];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($it as $f) {
            if ($f->isDir()) {
                $dirs[] = $f->getPathname();
            }
        }
        $signature = md5(implode('|', array_map(static fn ($d) => $d . ':' . filemtime($d), $dirs)));
        $sigFile = STORAGE_PATH . '/tmp/demo-library.sig';
        if (!$force && is_file($sigFile) && trim((string) file_get_contents($sigFile)) === $signature) {
            return null;
        }
        // Uma sincronização por vez.
        $lock = fopen(STORAGE_PATH . '/tmp/demo-library.lock', 'c');
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            return null;
        }
        @set_time_limit(300);
        try {
            $stats = self::doSync($root, $force);
            file_put_contents($sigFile, $signature);
            return $stats;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** $force = relê título, artista e duração de todos os arquivos (ex.: depois de melhorar o leitor). */
    private static function doSync(string $root, bool $force = false): array
    {
        $sid = self::id();
        $allowed = Media::allowedExtensions();
        // 1) Arquivos de áudio por pasta, sem duplicatas (mesmo nome em mp3 e wav: fica o mp3).
        $byDir = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            $ext = strtolower($f->getExtension());
            if (!$f->isFile() || !in_array($ext, $allowed, true)) {
                continue;
            }
            $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
            $dir = str_contains($rel, '/') ? substr($rel, 0, strrpos($rel, '/')) : '';
            $base = mb_strtolower(pathinfo($rel, PATHINFO_FILENAME));
            $cur = $byDir[$dir][$base] ?? null;
            if (!$cur || (self::PREFER[$ext] ?? 9) < (self::PREFER[$cur['ext']] ?? 9)) {
                $byDir[$dir][$base] = ['rel' => $rel, 'ext' => $ext, 'size' => $f->getSize()];
            }
        }

        // 2) Pastas: espelham as subpastas (arquivos soltos na raiz vão para "Demonstração").
        $folders = [];
        foreach (DB::all('SELECT id, parent_id, name FROM folders WHERE station_id = ?', [$sid]) as $row) {
            $folders[(int) $row['parent_id'] . '|' . $row['name']] = (int) $row['id'];
        }
        $folderIds = [];
        $ensure = static function (string $dir) use (&$ensure, &$folders, &$folderIds, $sid, $byDir): int {
            if (isset($folderIds[$dir])) {
                return $folderIds[$dir];
            }
            $parent = str_contains($dir, '/') ? $ensure(substr($dir, 0, strrpos($dir, '/'))) : 0;
            $name = $dir === '' ? 'Demonstração' : basename($dir);
            $key = $parent . '|' . $name;
            if (!isset($folders[$key])) {
                $folders[$key] = DB::insert('folders', ['station_id' => $sid, 'parent_id' => $parent ?: null, 'name' => $name, 'kind' => self::kindOf($name, array_keys($byDir[$dir] ?? []))]);
            }
            return $folderIds[$dir] = $folders[$key];
        };

        // 3) Arquivos: cria os novos, atualiza os alterados e remove os que sumiram.
        $existing = [];
        foreach (DB::all('SELECT id, stored_name, size_bytes, folder_id FROM media WHERE station_id = ?', [$sid]) as $row) {
            $existing[$row['stored_name']] = $row;
        }
        $seen = [];
        $added = $updated = 0;
        DB::transaction(static function () use ($byDir, $ensure, $existing, $sid, $root, $force, &$seen, &$added, &$updated): void {
            foreach ($byDir as $dir => $files) {
                $folderId = $ensure($dir);
                foreach ($files as $file) {
                    $seen[$file['rel']] = true;
                    $old = $existing[$file['rel']] ?? null;
                    if (!$force && $old && (int) $old['size_bytes'] === (int) $file['size'] && (int) $old['folder_id'] === $folderId) {
                        continue;
                    }
                    $full = $root . '/' . $file['rel'];
                    $meta = Media::describe(basename($file['rel']), $file['ext'] === 'mp3' ? Id3::read($full) : ['title' => null, 'artist' => null]);
                    $row = [
                        'folder_id' => $folderId,
                        'filename' => basename($file['rel']),
                        'mime' => Media::mimeOf($file['ext']),
                        'size_bytes' => (int) $file['size'],
                        'duration_ms' => AudioInfo::durationMs($full, $file['ext']),
                        'title' => $meta['title'],
                        'artist' => $meta['artist'],
                        'overlap_s' => $meta['overlap_s'],
                    ];
                    if ($old) {
                        DB::update('media', $row, 'id = ?', [$old['id']]);
                        $updated++;
                    } else {
                        DB::insert('media', [...$row, 'station_id' => $sid, 'stored_name' => $file['rel']]);
                        $added++;
                    }
                }
            }
        });
        $removed = 0;
        foreach ($existing as $rel => $row) {
            if (!isset($seen[$rel])) {
                DB::run('DELETE FROM media WHERE id = ?', [$row['id']]);
                $removed++;
            }
        }
        // Pastas que ficaram vazias (de baixo para cima).
        $used = array_flip(array_values($folderIds));
        foreach (array_reverse(DB::all('SELECT id FROM folders WHERE station_id = ? ORDER BY id', [$sid])) as $f) {
            if (!isset($used[(int) $f['id']]) && !DB::value('SELECT 1 FROM media WHERE folder_id = ?', [$f['id']]) && !DB::value('SELECT 1 FROM folders WHERE parent_id = ?', [$f['id']])) {
                DB::run('DELETE FROM folders WHERE id = ?', [$f['id']]);
            }
        }
        return ['added' => $added, 'updated' => $updated, 'removed' => $removed, 'files' => count($seen)];
    }

    /** Tipo da pasta pelo nome ou pelos arquivos (hora certa, temperatura, umidade). */
    private static function kindOf(string $name, array $basenames): string
    {
        $n = mb_strtolower($name);
        $ratio = static function (string $re) use ($basenames): float {
            return $basenames ? count(preg_grep($re, $basenames)) / count($basenames) : 0;
        };
        return match (true) {
            str_contains($n, 'hcerta') || str_contains($n, 'hora') || $ratio('/^(\d{4}|hrs\d{2}(_o)?|min\d{2})$/') > 0.8 => 'time',
            str_contains($n, 'temperatura') || $ratio('/^tmpn?\d{3}$/') > 0.8 => 'temperature',
            str_contains($n, 'umidade') || $ratio('/^hum\d{3}$/') > 0.8 => 'humidity',
            default => 'normal',
        };
    }
}
