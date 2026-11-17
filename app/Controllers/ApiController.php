<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\Session;
use App\Services\DemoLibrary;
use App\Services\Items;
use App\Services\Media;
use App\Services\Stations;
use App\Services\Weather;

/** API JSON usada pelo estúdio no navegador. */
final class ApiController
{
    private function sid(): int
    {
        return Auth::stationId();
    }

    public function bootstrap(): void
    {
        $st = station();
        $sid = (int) $st['id'];
        $u = user();
        Session::release();
        $setId = (int) $st['active_event_set_id'];
        $settings = Stations::settings($st);
        // Achado F2: a credencial de transmissão só vai ao navegador quando o envio está ligado no plano.
        if (!$st['streaming'] || !$settings['stream_enabled']) {
            $settings['stream_user'] = '';
            $settings['stream_pass'] = '';
            $settings['stream_url'] = '';
        }
        json_response([
            'ok' => true,
            'app' => app_name(),
            'station' => [
                'id' => $sid, 'name' => $st['name'], 'slug' => $st['slug'], 'timezone' => $st['timezone'],
                'plan' => $st['plan_name'], 'max_aux_players' => (int) $st['max_aux_players'], 'streaming' => (bool) $st['streaming'],
                'storage_mb' => (int) $st['storage_mb'], 'used_bytes' => Stations::usedBytes($sid),
            ],
            'user' => ['name' => $u['name'], 'role' => $u['role'], 'manager' => Auth::isManager()],
            'settings' => $settings,
            'folders' => $this->foldersOf($sid),
            'playlists' => DB::all('SELECT id, name, kind FROM playlists WHERE station_id = ? ORDER BY kind, name', [$sid]),
            'jingles' => array_map(static fn ($p) => ['page_no' => (int) $p['page_no'], 'name' => $p['name'], 'buttons' => json_decode((string) $p['buttons'], true) ?: []],
                DB::all('SELECT page_no, name, buttons FROM jingle_pages WHERE station_id = ? ORDER BY page_no', [$sid])),
            'event_sets' => DB::all('SELECT id, name FROM event_sets WHERE station_id = ? ORDER BY name', [$sid]),
            'event_set_id' => $setId,
            'events' => $this->eventsOf($sid, $setId),
            'sweepers' => $this->sweepersOf($sid),
            'weather' => Weather::current($st),
            'item_types' => Items::TYPES,
            'server_now' => (int) round(microtime(true) * 1000),
            'csrf' => Csrf::token(),
            'np_url' => url('np/' . $st['slug'] . '.json'),
            'chunk_bytes' => Media::CHUNK_BYTES,
            'max_file_bytes' => Media::MAX_FILE_BYTES,
        ]);
    }

    /** Pastas da rádio e, em seguida, as da demonstração (marcadas como compartilhadas, somente leitura). */
    private function foldersOf(int $sid): array
    {
        DemoLibrary::sync();
        $demo = DemoLibrary::id();
        $rows = DB::all('SELECT id, parent_id, name, kind, station_id FROM folders WHERE station_id IN (?, ?) ORDER BY CASE WHEN station_id = ? THEN 0 ELSE 1 END, name', [$sid, $demo, $sid]);
        return array_map(static fn ($f) => ['id' => (int) $f['id'], 'parent_id' => $f['parent_id'] !== null ? (int) $f['parent_id'] : null, 'name' => $f['name'], 'kind' => $f['kind'], 'shared' => (int) $f['station_id'] === $demo && $demo !== $sid], $rows);
    }

    private function eventsOf(int $sid, int $setId): array
    {
        return array_map(static function (array $e): array {
            $e['item'] = json_decode((string) $e['item'], true);
            foreach (['id', 'enabled', 'hours_mask', 'weekdays_mask', 'max_wait_s'] as $k) {
                $e[$k] = (int) $e[$k];
            }
            return $e;
        }, DB::all('SELECT id, name, enabled, start_at, expires_at, periodicity, hours_mask, weekdays_mask, item, wait_mode, priority, max_wait_s
            FROM events WHERE station_id = ? AND event_set_id = ? ORDER BY start_at', [$sid, $setId]));
    }

    private function sweepersOf(int $sid): object
    {
        $out = [];
        foreach (DB::all('SELECT media_id, item, offset_s FROM sweepers WHERE station_id = ?', [$sid]) as $s) {
            $out[(int) $s['media_id']][] = ['item' => json_decode((string) $s['item'], true), 'offset_s' => (float) $s['offset_s']];
        }
        return (object) $out;
    }

    /** Busca na biblioteca: pasta (com subpastas) e/ou texto com curinga "*" em nome, título e artista. */
    public function library(): void
    {
        $sid = $this->sid();
        Session::release();
        // Lê os arquivos da rádio e os de demonstração.
        $owners = DemoLibrary::readable($sid);
        $where = ['station_id IN (' . implode(',', array_fill(0, count($owners), '?')) . ')'];
        $params = $owners;
        $folder = (int) input('folder', 0);
        if ($folder > 0) {
            $ids = Media::folderTree($sid, $folder, true);
            $ids = input('recursive') === '1' ? $ids : array_intersect($ids, [$folder]);
            if (!$ids) {
                json_response(['ok' => true, 'items' => []]);
            }
            $where[] = 'folder_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            array_push($params, ...$ids);
        }
        $q = trim((string) input('q', ''));
        if ($q !== '') {
            // "!" como caractere de escape funciona igual no MySQL e no SQLite ("\" quebra no MySQL).
            $like = str_replace(['!', '%', '_', '*'], ['!!', '!%', '!_', '%'], $q);
            $like = str_contains($q, '*') ? $like : '%' . $like . '%';
            $where[] = "(filename LIKE ? ESCAPE '!' OR title LIKE ? ESCAPE '!' OR artist LIKE ? ESCAPE '!')";
            array_push($params, $like, $like, $like);
        }
        // Os arquivos da própria rádio aparecem antes dos de demonstração.
        $rows = DB::all('SELECT * FROM media WHERE ' . implode(' AND ', $where) . ' ORDER BY CASE WHEN station_id = ? THEN 0 ELSE 1 END, artist, title, filename LIMIT 2000', [...$params, $sid]);
        json_response(['ok' => true, 'items' => array_map([Media::class, 'toItem'], $rows)]);
    }

    public function upload(): void
    {
        $st = station();
        Session::release();
        $file = $_FILES['chunk']['tmp_name'] ?? '';
        if (!is_string($file) || $file === '' || !is_uploaded_file($file)) {
            json_response(['ok' => false, 'error' => 'Parte do arquivo não recebida.'], 400);
        }
        try {
            $media = Media::receiveChunk($st, $_POST, $file);
        } catch (\RuntimeException $e) {
            json_response(['ok' => false, 'error' => $e->getMessage()], 422);
        }
        json_response(['ok' => true, 'done' => $media !== null, 'item' => $media ? Media::toItem($media) : null]);
    }

    public function playlist(int $id): void
    {
        $p = DB::one('SELECT id, name, kind, items FROM playlists WHERE id = ? AND station_id = ?', [$id, $this->sid()]);
        Session::release();
        if (!$p) {
            abort(404, 'Lista não encontrada.');
        }
        $p['items'] = json_decode((string) $p['items'], true) ?: [];
        json_response(['ok' => true, 'playlist' => $p]);
    }

    /** Cria (sem id) ou grava uma lista, rotação ou sequência. */
    public function savePlaylist(): void
    {
        $sid = $this->sid();
        $in = json_input();
        $name = mb_substr(trim((string) ($in['name'] ?? '')), 0, 120);
        $kind = in_array($in['kind'] ?? null, ['list', 'rotation', 'sequence'], true) ? $in['kind'] : 'list';
        $items = Items::sanitizeList($in['items'] ?? [], $sid);
        $id = (int) ($in['id'] ?? 0);
        if ($id > 0) {
            $p = DB::one('SELECT id FROM playlists WHERE id = ? AND station_id = ?', [$id, $sid]);
            if (!$p) {
                abort(404, 'Lista não encontrada.');
            }
            $data = ['items' => json_encode($items, JSON_UNESCAPED_UNICODE), 'updated_at' => now()];
            if ($name !== '') {
                $data['name'] = $name;
            }
            DB::update('playlists', $data, 'id = ?', [$id]);
        } else {
            $id = DB::insert('playlists', ['station_id' => $sid, 'name' => $name ?: 'Nova lista', 'kind' => $kind, 'items' => json_encode($items, JSON_UNESCAPED_UNICODE)]);
        }
        json_response(['ok' => true, 'id' => $id, 'items' => $items, 'playlists' => DB::all('SELECT id, name, kind FROM playlists WHERE station_id = ? ORDER BY kind, name', [$sid])]);
    }

    /** Rotação: devolve o próximo item e avança o ponteiro (repete em ciclo). */
    public function rotationNext(int $id): void
    {
        $sid = $this->sid();
        Session::release();
        $item = DB::transaction(static function () use ($id, $sid): ?array {
            $p = DB::one('SELECT items, rotation_pos FROM playlists WHERE id = ? AND station_id = ?', [$id, $sid]);
            $items = $p ? (json_decode((string) $p['items'], true) ?: []) : [];
            if (!$items) {
                return null;
            }
            $pos = (int) $p['rotation_pos'] % count($items);
            DB::update('playlists', ['rotation_pos' => ($pos + 1) % count($items)], 'id = ?', [$id]);
            return $items[$pos];
        });
        json_response(['ok' => $item !== null, 'item' => $item, 'error' => $item ? null : 'Rotação vazia.']);
    }

    public function random(int $folder_id): void
    {
        $sid = $this->sid();
        Session::release();
        $m = Media::randomFrom($sid, $folder_id);
        json_response(['ok' => $m !== null, 'item' => $m ? Media::toItem($m) : null, 'error' => $m ? null : 'Pasta vazia.']);
    }

    /**
     * Locuções de hora, temperatura e umidade.
     * Hora certa aceita dois padrões de arquivos:
     *  - um arquivo por minuto, "HHMM" (ex.: 1435.mp3; em 12 horas, 0235.mp3 também serve para 14:35);
     *  - hora + minuto separados: HRSxx (ou HRSxx_O na hora cheia) seguido de MINxx.
     * Os arquivos da própria rádio têm prioridade sobre os da demonstração.
     */
    public function tth(string $kind): void
    {
        $st = station();
        $sid = (int) $st['id'];
        Session::release();
        $plans = [];   // cada plano = lista de partes; cada parte = nomes alternativos
        $label = '';
        if ($kind === 'time') {
            $h = max(0, min(23, (int) input('h', date('G'))));
            $m = max(0, min(59, (int) input('m', date('i'))));
            $label = sprintf('Hora certa %02d:%02d', $h, $m);
            $plans[] = [[sprintf('%02d%02d', $h, $m)]];
            if ($h > 12) {
                $plans[] = [[sprintf('%02d%02d', $h - 12, $m)]];
            }
            $plans[] = $m === 0 ? [[sprintf('HRS%02d_O', $h), sprintf('HRS%02d', $h)]] : [[sprintf('HRS%02d', $h)], [sprintf('MIN%02d', $m)]];
        } else {
            $w = Weather::current($st);
            if (!$w['valid'] || $w['temp'] === null) {
                json_response(['ok' => false, 'error' => 'Dados do tempo desatualizados; locução ignorada.']);
            }
            if ($kind === 'temperature') {
                $t = (int) $w['temp'];
                $label = 'Temperatura ' . $t . '°' . $w['unit'];
                $plans[] = [[$t < 0 ? sprintf('TMPN%03d', abs($t)) : sprintf('TMP%03d', $t)]];
            } else {
                $label = 'Umidade ' . $w['hum'] . '%';
                $plans[] = [[sprintf('HUM%03d', max(0, min(100, (int) $w['hum'])))]];
            }
        }
        $owners = DemoLibrary::readable($sid);
        $in = implode(',', array_fill(0, count($owners), '?'));
        $files = DB::all("SELECT m.* FROM media m JOIN folders f ON f.id = m.folder_id WHERE m.station_id IN ($in) AND f.kind = ?
            ORDER BY CASE WHEN m.station_id = ? THEN 0 ELSE 1 END, m.id", [...$owners, $kind, $sid]);
        // 1ª tentativa: só os arquivos da própria rádio; 2ª: incluindo os da demonstração.
        $own = $all = [];
        foreach ($files as $f) {
            $key = strtoupper(pathinfo((string) $f['filename'], PATHINFO_FILENAME));
            $all[$key] ??= $f;
            if ((int) $f['station_id'] === $sid) {
                $own[$key] ??= $f;
            }
        }
        foreach ([$own, $all] as $byName) {
            foreach ($plans as $parts) {
                $items = [];
                foreach ($parts as $alternatives) {
                    $found = null;
                    foreach ($alternatives as $n) {
                        $found ??= $byName[$n] ?? null;
                    }
                    if (!$found) {
                        continue 2;
                    }
                    $items[] = Media::toItem($found);
                }
                json_response(['ok' => true, 'label' => $label, 'items' => $items]);
            }
        }
        $expected = $kind === 'time' ? 'HHMM (ex.: 1435.mp3) ou HRSxx + MINxx' : $plans[0][0][0] . '.mp3';
        json_response(['ok' => false, 'error' => "Arquivo de locução não encontrado para “{$label}”. Envie $expected numa pasta do tipo " . ['time' => 'Hora certa', 'temperature' => 'Temperatura', 'humidity' => 'Umidade'][$kind]]);
    }

    public function events(): void
    {
        $sid = $this->sid();
        Session::release();
        $set = (int) input('set', 0);
        if (!DB::value('SELECT 1 FROM event_sets WHERE id = ? AND station_id = ?', [$set, $sid])) {
            abort(404, 'Grade não encontrada.');
        }
        json_response(['ok' => true, 'event_set_id' => $set, 'events' => $this->eventsOf($sid, $set)]);
    }

    public function activateEvents(): void
    {
        $sid = $this->sid();
        $set = (int) (json_input()['event_set_id'] ?? 0);
        if (!DB::value('SELECT 1 FROM event_sets WHERE id = ? AND station_id = ?', [$set, $sid])) {
            abort(404, 'Grade não encontrada.');
        }
        DB::update('stations', ['active_event_set_id' => $set], 'id = ?', [$sid]);
        json_response(['ok' => true]);
    }

    public function log(): void
    {
        $st = station();
        Session::release();
        if (!Stations::settings($st)['log_enabled']) {
            json_response(['ok' => true, 'skipped' => true]);
        }
        $entries = array_slice((array) (json_input()['entries'] ?? []), 0, 200);
        // O relatório vai para anunciantes: a hora vem do navegador (que guarda a fila se a internet cair),
        // mas só é aceita se estiver perto do relógio do servidor no fuso da rádio.
        $tz = new \DateTimeZone((string) $st['timezone']);
        $serverNow = new \DateTimeImmutable('now', $tz);
        foreach ($entries as $e) {
            if (!is_array($e)) {
                continue;
            }
            $at = (string) ($e['at'] ?? '');
            $when = preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $at) ? \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $at, $tz) : false;
            if (!$when || $when > $serverNow->modify('+2 minutes') || $when < $serverNow->modify('-24 hours')) {
                $at = $serverNow->format('Y-m-d H:i:s');
            }
            DB::insert('play_log', [
                'station_id' => (int) $st['id'],
                'played_at' => $at,
                'source' => mb_substr((string) ($e['source'] ?? 'lista'), 0, 20),
                'kind' => mb_substr((string) ($e['kind'] ?? 'track'), 0, 20),
                'title' => mb_substr((string) ($e['title'] ?? '?'), 0, 255),
                'media_id' => isset($e['media_id']) ? (int) $e['media_id'] ?: null : null,
                'status' => ($e['status'] ?? 'ok') === 'error' ? 'error' : 'ok',
                'detail' => isset($e['detail']) ? mb_substr((string) $e['detail'], 0, 255) : null,
            ]);
        }
        json_response(['ok' => true]);
    }

    public function nowPlaying(): void
    {
        $sid = $this->sid();
        Session::release();
        $in = json_input();
        $np = [
            'title' => mb_substr((string) ($in['title'] ?? ''), 0, 255),
            'artist' => mb_substr((string) ($in['artist'] ?? ''), 0, 255),
            'duration_ms' => (int) ($in['duration_ms'] ?? 0),
            'started_at' => date('c'),
            'next' => mb_substr((string) ($in['next'] ?? ''), 0, 255),
        ];
        DB::update('station_state', ['now_playing' => json_encode($np, JSON_UNESCAPED_UNICODE), 'np_updated_at' => now()], 'station_id = ?', [$sid]);
        json_response(['ok' => true]);
    }

    /** Controle de instância principal: só um estúdio dispara os eventos da rádio. */
    public function heartbeat(): void
    {
        $sid = $this->sid();
        Session::release();
        $token = (string) (json_input()['token'] ?? '');
        $claim = (bool) (json_input()['claim'] ?? false);
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            abort(400, 'Token inválido.');
        }
        $leader = DB::transaction(static function () use ($sid, $token, $claim): bool {
            $s = DB::one('SELECT leader_token, leader_seen_at FROM station_state WHERE station_id = ?', [$sid]);
            $stale = !$s || !$s['leader_seen_at'] || time() - strtotime((string) $s['leader_seen_at']) > 25;
            if ($claim || $stale || ($s['leader_token'] ?? '') === $token) {
                DB::update('station_state', ['leader_token' => $token, 'leader_seen_at' => now()], 'station_id = ?', [$sid]);
                return true;
            }
            return false;
        });
        json_response(['ok' => true, 'leader' => $leader, 'server_now' => (int) round(microtime(true) * 1000)]);
    }

    /** O navegador mede a duração real do arquivo e grava ("Atualizar duração"). */
    public function mediaDuration(int $id): void
    {
        $ms = max(0, min(86400000, (int) (json_input()['duration_ms'] ?? 0)));
        // Também corrige a duração de um áudio de demonstração (dado técnico, igual para todas as rádios).
        if (Media::readable($this->sid(), $id)) {
            DB::update('media', ['duration_ms' => $ms], 'id = ?', [$id]);
        }
        json_response(['ok' => true]);
    }

    /** Arrastar um arquivo para um botão de vinheta no estúdio. */
    public function saveJinglePage(): void
    {
        $sid = $this->sid();
        $in = json_input();
        $page = max(0, min(9, (int) ($in['page_no'] ?? 0)));
        $buttons = [];
        foreach (array_slice((array) ($in['buttons'] ?? []), 0, 9) as $b) {
            $slot = (int) ($b['slot'] ?? -1);
            $item = is_array($b['item'] ?? null) && in_array($b['item']['type'] ?? '', ['track', 'time', 'temperature', 'humidity'], true)
                ? Items::sanitizeOne($b['item'], $sid) : null;
            if ($slot < 0 || $slot > 8 || !$item) {
                continue;
            }
            $mode = in_array($b['mode'] ?? '', ['normal', 'loop', 'sweeper'], true) ? $b['mode'] : 'normal';
            $buttons[$slot] = ['slot' => $slot, 'label' => mb_substr((string) ($b['label'] ?? ''), 0, 24) ?: mb_substr((string) $item['title'], 0, 24), 'mode' => $mode, 'item' => $item];
        }
        DB::update('jingle_pages', ['buttons' => json_encode(array_values($buttons), JSON_UNESCAPED_UNICODE)], 'station_id = ? AND page_no = ?', [$sid, $page]);
        json_response(['ok' => true]);
    }

    public function saveState(): void
    {
        $in = json_input();
        $changes = array_intersect_key($in, array_flip(['last_playlist_id']));
        Stations::saveSettings($this->sid(), $changes);
        json_response(['ok' => true]);
    }
}
