<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

final class Media
{
    public const MAX_FILE_BYTES = 300 * 1024 * 1024;
    public const CHUNK_BYTES = 1024 * 1024;   // 1 MB por parte: passa em qualquer limite de upload do cPanel
    public const PART_TTL = 7200;             // envio parado há 2 h é descartado
    public const MAX_OPEN_UPLOADS = 3;        // envios simultâneos por rádio

    /** Formatos que os navegadores tocam (WMA não é suportado pelos navegadores). */
    private const TYPES = [
        'mp3' => 'audio/mpeg', 'ogg' => 'audio/ogg', 'oga' => 'audio/ogg', 'opus' => 'audio/ogg',
        'wav' => 'audio/wav', 'flac' => 'audio/flac', 'm4a' => 'audio/mp4', 'aac' => 'audio/aac', 'webm' => 'audio/webm',
    ];

    public static function dir(int $stationId): string
    {
        $d = STORAGE_PATH . '/media/' . $stationId;
        if (!is_dir($d)) {
            mkdir($d, 0750, true);
        }
        return $d;
    }

    public static function path(array $media): string
    {
        // Áudios de demonstração ficam em audios/ (caminho relativo, validado para não sair da pasta).
        if (DemoLibrary::isDemo((int) $media['station_id'])) {
            return DemoLibrary::path((string) $media['stored_name']) ?? '';
        }
        return self::dir((int) $media['station_id']) . '/' . basename((string) $media['stored_name']);
    }

    public static function allowedExtensions(): array
    {
        return array_keys(self::TYPES);
    }

    public static function mimeOf(string $ext): string
    {
        return self::TYPES[strtolower($ext)] ?? 'application/octet-stream';
    }

    /**
     * Título, artista e sobreposição a partir das tags ID3 e do nome do arquivo.
     * "Artista - Título~2.5.mp3" vira artista, título e 2,5 s de sobreposição.
     */
    public static function describe(string $filename, array $tags): array
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $overlap = null;
        if (preg_match('/~(\d+(?:[.,]\d+)?)$/', $base, $m)) {
            $overlap = (float) str_replace(',', '.', $m[1]);
            $base = substr($base, 0, -strlen($m[0]));
        }
        $title = $tags['title'] ?? null;
        $artist = $tags['artist'] ?? null;
        if (!$title && str_contains($base, ' - ')) {
            [$a, $t] = array_map('trim', explode(' - ', $base, 2));
            $artist = $artist ?: $a;
            $title = $t;
        }
        return ['title' => $title ? mb_substr($title, 0, 255) : null, 'artist' => $artist ? mb_substr($artist, 0, 255) : null, 'overlap_s' => $overlap];
    }

    /**
     * Lista para as caixas de seleção (eventos, vinhetas, sweepers): primeiro os arquivos da rádio,
     * depois os da demonstração ("Demonstração › pasta"). Pastas de locução da demo ficam de fora,
     * porque já são usadas pelas opções "Hora certa", "Temperatura" e "Umidade".
     */
    public static function pickerList(int $stationId): array
    {
        $own = DB::all('SELECT m.id, m.filename, f.name AS folder FROM media m JOIN folders f ON f.id = m.folder_id WHERE m.station_id = ? ORDER BY f.name, m.filename LIMIT 5000', [$stationId]);
        $demo = DemoLibrary::id();
        if (!$demo || $demo === $stationId) {
            return $own;
        }
        $shared = DB::all("SELECT m.id, m.filename, f.name AS folder FROM media m JOIN folders f ON f.id = m.folder_id
            WHERE m.station_id = ? AND f.kind = 'normal' ORDER BY f.name, m.filename LIMIT 3000", [$demo]);
        foreach ($shared as &$row) {
            $row['folder'] = 'Demonstração › ' . $row['folder']; // concatenação em PHP: funciona igual no MySQL e no SQLite
        }
        return [...$own, ...$shared];
    }

    /** Busca uma mídia que a rádio pode ler: dela ou da demonstração. */
    public static function readable(int $stationId, int $mediaId): ?array
    {
        $ids = DemoLibrary::readable($stationId);
        $in = implode(',', array_fill(0, count($ids), '?'));
        return DB::one("SELECT * FROM media WHERE id = ? AND station_id IN ($in)", [$mediaId, ...$ids]);
    }

    /** Detecta o formato pelo conteúdo (não confia na extensão enviada). */
    public static function sniff(string $file): ?string
    {
        $h = (string) file_get_contents($file, false, null, 0, 64);
        return match (true) {
            str_starts_with($h, 'ID3'), strlen($h) > 1 && ord($h[0]) === 0xFF && (ord($h[1]) & 0xE0) === 0xE0 && (ord($h[1]) & 0x06) !== 0 => 'mp3',
            str_starts_with($h, 'OggS') => 'ogg',
            str_starts_with($h, 'RIFF') && substr($h, 8, 4) === 'WAVE' => 'wav',
            str_starts_with($h, 'fLaC') => 'flac',
            substr($h, 4, 4) === 'ftyp' => 'm4a',
            str_starts_with($h, "\x1A\x45\xDF\xA3") => 'webm',
            strlen($h) > 1 && ord($h[0]) === 0xFF && (ord($h[1]) & 0xF6) === 0xF0 => 'aac',
            default => null,
        };
    }

    /**
     * Recebe uma parte do upload. Retorna null enquanto faltam partes, ou o registro criado na última.
     * @throws \RuntimeException com mensagem para o usuário
     */
    public static function receiveChunk(array $station, array $meta, string $chunkFile): ?array
    {
        $sid = (int) $station['id'];
        $uploadId = (string) ($meta['upload_id'] ?? '');
        $index = (int) ($meta['index'] ?? -1);
        $total = (int) ($meta['total'] ?? 0);
        $size = (int) ($meta['size'] ?? 0);
        if (!preg_match('/^[a-f0-9]{32}$/', $uploadId) || $index < 0 || $total < 1 || $index >= $total || $total > 400) {
            throw new \RuntimeException('Upload inválido.');
        }
        if ($size <= 0 || $size > self::MAX_FILE_BYTES) {
            throw new \RuntimeException('Arquivo maior que o limite de ' . format_bytes(self::MAX_FILE_BYTES) . '.');
        }
        $folder = DB::one('SELECT id FROM folders WHERE id = ? AND station_id = ?', [(int) ($meta['folder_id'] ?? 0), $sid]);
        if (!$folder) {
            throw new \RuntimeException('A pasta escolhida não existe mais. Recarregue a página.');
        }
        $tmpDir = STORAGE_PATH . '/tmp/' . $sid;
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0750, true);
        }
        $part = $tmpDir . '/' . $uploadId . '.part';
        // Achado F3: a cota conta também os envios em andamento, e envios abandonados expiram.
        $pendingBytes = 0;
        $open = 0;
        foreach (glob($tmpDir . '/*.part') ?: [] as $f) {
            if (filemtime($f) < time() - self::PART_TTL) {
                @unlink($f);
                continue;
            }
            if ($f !== $part) {
                $pendingBytes += (int) filesize($f);
                $open++;
            }
        }
        if ($index === 0 && $open >= self::MAX_OPEN_UPLOADS) {
            throw new \RuntimeException('Já há ' . self::MAX_OPEN_UPLOADS . ' envios em andamento. Espere terminarem e tente de novo.');
        }
        $quota = (int) $station['storage_mb'] * 1024 * 1024;
        if (Stations::usedBytes($sid) + $pendingBytes + $size > $quota) {
            throw new \RuntimeException('Não cabe no espaço do plano (' . format_bytes($quota) . '). Apague arquivos que não usa ou fale com o suporte sobre outro plano.');
        }
        $expectedOffset = is_file($part) ? filesize($part) : 0;
        if ($index === 0 && $expectedOffset > 0) {
            unlink($part);
            $expectedOffset = 0;
        }
        if ($index * self::CHUNK_BYTES !== $expectedOffset) {
            throw new \RuntimeException('Partes fora de ordem. Envie o arquivo de novo.');
        }
        $data = (string) file_get_contents($chunkFile);
        if (strlen($data) > self::CHUNK_BYTES || $expectedOffset + strlen($data) > $size) {
            @unlink($part);
            throw new \RuntimeException('Parte com tamanho inválido.');
        }
        file_put_contents($part, $data, FILE_APPEND | LOCK_EX);
        clearstatcache(true, $part);
        if ($index < $total - 1) {
            return null;
        }
        if (filesize($part) !== $size) {
            @unlink($part);
            throw new \RuntimeException('O arquivo chegou incompleto. Tente de novo.');
        }
        // Recheca a cota com trava: dois envios terminando juntos não estouram o limite.
        $lock = fopen($tmpDir . '/.quota.lock', 'c');
        flock($lock, LOCK_EX);
        try {
            if (Stations::usedBytes($sid) + $size > $quota) {
                @unlink($part);
                throw new \RuntimeException('Não cabe no espaço do plano (' . format_bytes($quota) . '). Apague arquivos que não usa ou fale com o suporte.');
            }
            return self::ingest($sid, (int) $folder['id'], $part, (string) ($meta['filename'] ?? 'audio'), (int) ($meta['duration_ms'] ?? 0));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public static function ingest(int $stationId, int $folderId, string $file, string $originalName, int $durationMs): array
    {
        $type = self::sniff($file);
        if ($type === null) {
            @unlink($file);
            throw new \RuntimeException('Formato não reconhecido. Use MP3, OGG, WAV, FLAC, M4A/AAC ou WEBM.');
        }
        $name = mb_substr(preg_replace('/[\x00-\x1F\x7F\/\\\\]/u', '', basename($originalName)) ?: 'audio.' . $type, 0, 200);
        $stored = bin2hex(random_bytes(16)) . '.' . $type;
        $dest = self::dir($stationId) . '/' . $stored;
        if (!rename($file, $dest)) {
            throw new \RuntimeException('Não foi possível gravar o arquivo.');
        }
        chmod($dest, 0640);
        $meta = self::describe($name, $type === 'mp3' ? Id3::read($dest) : ['title' => null, 'artist' => null]);
        $id = DB::insert('media', [
            'station_id' => $stationId,
            'folder_id' => $folderId,
            'filename' => $name,
            'stored_name' => $stored,
            'mime' => self::TYPES[$type],
            'size_bytes' => filesize($dest),
            'duration_ms' => max(0, min($durationMs ?: AudioInfo::durationMs($dest, $type), 86400000)),
            'title' => $meta['title'],
            'artist' => $meta['artist'],
            'overlap_s' => $meta['overlap_s'],
        ]);
        return DB::one('SELECT * FROM media WHERE id = ?', [$id]);
    }

    public static function delete(array $media): void
    {
        if (DemoLibrary::isDemo((int) $media['station_id'])) {
            return; // a demonstração é somente leitura
        }
        @unlink(self::path($media));
        DB::run('DELETE FROM media WHERE id = ? AND station_id = ?', [$media['id'], $media['station_id']]);
    }

    /** Entrega o áudio com suporte a Range (necessário para avançar/voltar no player). */
    public static function stream(array $media): never
    {
        $path = self::path($media);
        if ($path === '' || !is_file($path)) {
            abort(404);
        }
        $size = filesize($path);
        $start = 0;
        $end = $size - 1;
        header('Content-Type: ' . $media['mime']);
        header('Accept-Ranges: bytes');
        header('Cache-Control: private, max-age=86400');
        header('Content-Disposition: inline; filename="media-' . (int) $media['id'] . '"');
        if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
            if ($m[1] === '' && $m[2] !== '') {
                $start = max(0, $size - (int) $m[2]);
            } else {
                $start = (int) $m[1];
                $end = $m[2] !== '' ? min((int) $m[2], $size - 1) : $end;
            }
            if ($start > $end || $start >= $size) {
                http_response_code(416);
                header("Content-Range: bytes */$size");
                exit;
            }
            http_response_code(206);
            header("Content-Range: bytes $start-$end/$size");
        }
        header('Content-Length: ' . ($end - $start + 1));
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
            exit;
        }
        @set_time_limit(0);
        while (ob_get_level()) {
            ob_end_clean();
        }
        $fh = fopen($path, 'rb');
        fseek($fh, $start);
        $left = $end - $start + 1;
        while ($left > 0 && !feof($fh) && !connection_aborted()) {
            $buf = fread($fh, (int) min(65536, $left));
            echo $buf;
            flush();
            $left -= strlen($buf);
        }
        fclose($fh);
        exit;
    }

    /** Sorteia uma faixa da pasta (e subpastas) sem repetir até tocar todas. */
    public static function randomFrom(int $stationId, int $folderId): ?array
    {
        $ids = self::folderTree($stationId, $folderId, true);
        if (!$ids) {
            return null;
        }
        $owners = DemoLibrary::readable($stationId);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $inOwners = implode(',', array_fill(0, count($owners), '?'));
        $all = array_map('intval', array_column(DB::all("SELECT id FROM media WHERE station_id IN ($inOwners) AND folder_id IN ($in)", [...$owners, ...$ids]), 'id'));
        if (!$all) {
            return null;
        }
        return DB::transaction(static function () use ($stationId, $folderId, $all): ?array {
            $state = json_decode((string) DB::value('SELECT random_state FROM station_state WHERE station_id = ?', [$stationId]), true) ?: [];
            $played = array_values(array_intersect($state[$folderId] ?? [], $all));
            $pool = array_values(array_diff($all, $played));
            if (!$pool) {
                $played = [];
                $pool = $all;
            }
            $pick = $pool[random_int(0, count($pool) - 1)];
            $played[] = $pick;
            $state[$folderId] = $played;
            DB::update('station_state', ['random_state' => json_encode($state)], 'station_id = ?', [$stationId]);
            return DB::one('SELECT * FROM media WHERE id = ?', [$pick]);
        });
    }

    /**
     * IDs da pasta e de todas as subpastas.
     * $readOnly = true também aceita pastas da demonstração (só para ler/tocar, nunca para apagar).
     */
    public static function folderTree(int $stationId, int $folderId, bool $readOnly = false): array
    {
        $owners = $readOnly ? DemoLibrary::readable($stationId) : [$stationId];
        $folders = DB::all('SELECT id, parent_id FROM folders WHERE station_id IN (' . implode(',', array_fill(0, count($owners), '?')) . ')', $owners);
        $children = [];
        foreach ($folders as $f) {
            $children[(int) $f['parent_id']][] = (int) $f['id'];
        }
        if (!in_array($folderId, array_map('intval', array_column($folders, 'id')), true)) {
            return [];
        }
        $out = [];
        $stack = [$folderId];
        while ($stack) {
            $id = array_pop($stack);
            $out[] = $id;
            foreach ($children[$id] ?? [] as $c) {
                $stack[] = $c;
            }
        }
        return $out;
    }

    /** Formato do item usado pelo estúdio para uma faixa da biblioteca. */
    public static function toItem(array $m): array
    {
        return [
            'type' => 'track',
            'media_id' => (int) $m['id'],
            'title' => $m['title'] ?: preg_replace('/~\d+(?:[.,]\d+)?$/', '', pathinfo((string) $m['filename'], PATHINFO_FILENAME)),
            'artist' => $m['artist'] ?: '',
            'filename' => $m['filename'],
            'duration_ms' => (int) $m['duration_ms'],
            'overlap_s' => $m['overlap_s'] !== null ? (float) $m['overlap_s'] : null,
            'folder_id' => (int) $m['folder_id'],
            'demo' => DemoLibrary::isDemo((int) $m['station_id']),
        ];
    }
}
