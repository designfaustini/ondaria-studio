<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Services\Items;

final class EventsController
{
    public function index(): string
    {
        $sid = Auth::stationId();
        $st = station();
        $sets = DB::all('SELECT s.*, (SELECT COUNT(*) FROM events e WHERE e.event_set_id = s.id) AS total FROM event_sets s WHERE s.station_id = ? ORDER BY s.name', [$sid]);
        $setId = (int) input('grade', 0) ?: (int) $st['active_event_set_id'];
        if (!in_array($setId, array_map('intval', array_column($sets, 'id')), true)) {
            $setId = (int) ($sets[0]['id'] ?? 0);
        }
        $events = DB::all('SELECT * FROM events WHERE station_id = ? AND event_set_id = ? ORDER BY start_at', [$sid, $setId]);
        foreach ($events as &$e) {
            $e['item'] = json_decode((string) $e['item'], true);
            $e['expired'] = $e['expires_at'] !== null && $e['expires_at'] < now();
        }
        return view('events/index', ['title' => 'Eventos', 'sets' => $sets, 'setId' => $setId, 'activeSet' => (int) $st['active_event_set_id'], 'events' => $events]);
    }

    public function form(int $id = 0): string
    {
        $sid = Auth::stationId();
        $event = $id ? DB::one('SELECT * FROM events WHERE id = ? AND station_id = ?', [$id, $sid]) : null;
        if ($id && !$event) {
            abort(404);
        }
        $event ??= [
            // Padrão = o evento mais comum: hora certa de hora em hora, esperando a música acabar.
            'id' => 0, 'name' => '', 'enabled' => 1, 'start_at' => date('Y-m-d') . ' 00:00:00', 'expires_at' => null,
            'periodicity' => 'hourly', 'hours_mask' => 0, 'weekdays_mask' => 127, 'item' => '{"type":"time"}',
            'wait_mode' => 'delayed', 'priority' => 'low', 'max_wait_s' => 300, 'event_set_id' => (int) input('grade', station()['active_event_set_id']),
        ];
        $event['item'] = json_decode((string) $event['item'], true) ?: ['type' => 'time'];
        return view('events/form', [
            'title' => $id ? 'Editar evento' : 'Novo evento',
            'event' => $event,
            'sets' => DB::all('SELECT id, name FROM event_sets WHERE station_id = ? ORDER BY name', [$sid]),
            'folders' => array_map(static fn ($f) => [...$f, 'name' => (int) $f['station_id'] === $sid ? $f['name'] : 'Demonstração › ' . $f['name']],
                DB::all('SELECT id, name, kind, station_id FROM folders WHERE station_id IN (?, ?) ORDER BY CASE WHEN station_id = ? THEN 0 ELSE 1 END, name', [$sid, \App\Services\DemoLibrary::id(), $sid])),
            'media' => \App\Services\Media::pickerList($sid),
            'playlists' => DB::all('SELECT id, name, kind FROM playlists WHERE station_id = ? ORDER BY kind, name', [$sid]),
            'types' => Items::TYPES,
        ]);
    }

    public function save(): void
    {
        $sid = Auth::stationId();
        $id = (int) input('id', 0);
        if ($id && !DB::value('SELECT 1 FROM events WHERE id = ? AND station_id = ?', [$id, $sid])) {
            abort(404);
        }
        $set = (int) input('event_set_id', 0);
        if (!DB::value('SELECT 1 FROM event_sets WHERE id = ? AND station_id = ?', [$set, $sid])) {
            abort(422, 'Grade inválida.');
        }
        $type = (string) input('item_type', '');
        $item = Items::sanitizeOne([
            'type' => $type,
            'media_id' => (int) input('media_id', 0),
            'folder_id' => (int) input('folder_id', 0),
            'playlist_id' => (int) input('playlist_id', 0),
            'event_set_id' => (int) input('target_set_id', 0),
            'seconds' => (int) input('seconds', 0),
            'url' => (string) input('url', ''),
            'text' => (string) input('text', ''),
            'title' => (string) input('stream_title', ''),
        ], $sid);
        $start = $this->dt((string) input('start_date'), (string) input('start_time'));
        $errors = [];
        if (!$item) $errors[] = 'Não deu para salvar: escolha o que o evento vai tocar (um arquivo, pasta, lista ou endereço que exista).';
        if (!$start) $errors[] = 'Não deu para salvar: a data ou o horário de início está incompleto.';
        $expires = input('expires') ? $this->dt((string) input('expires_date'), (string) input('expires_time')) : null;
        if (input('expires') && !$expires) $errors[] = 'Não deu para salvar: a data de término está incompleta.';
        if ($start && $expires && $expires <= $start) $errors[] = 'Não deu para salvar: a data de término precisa ser depois do início.';
        $hours = 0;
        foreach ((array) ($_POST['hours'] ?? []) as $h) {
            $hours |= 1 << max(0, min(23, (int) $h));
        }
        $days = 0;
        foreach ((array) ($_POST['weekdays'] ?? []) as $d) {
            $days |= 1 << max(0, min(6, (int) $d));
        }
        $periodicity = in_array(input('periodicity'), ['once', 'daily', 'hourly', 'hours'], true) ? (string) input('periodicity') : 'daily';
        if ($periodicity === 'hours' && $hours === 0) $errors[] = 'Marque pelo menos uma hora.';
        if ($errors) {
            foreach ($errors as $err) {
                flash('error', $err);
            }
            back();
        }
        $data = [
            'event_set_id' => $set,
            'name' => mb_substr(trim((string) input('name', '')), 0, 160) ?: $item['title'],
            'enabled' => input('enabled') ? 1 : 0,
            'start_at' => $start,
            'expires_at' => $expires,
            'periodicity' => $periodicity,
            'hours_mask' => $hours,
            'weekdays_mask' => $days ?: 127,
            'item' => json_encode($item, JSON_UNESCAPED_UNICODE),
            'wait_mode' => input('wait_mode') === 'immediate' ? 'immediate' : 'delayed',
            'priority' => input('priority') === 'high' ? 'high' : 'low',
            'max_wait_s' => max(0, min(86400, (int) round((float) str_replace(',', '.', (string) input('max_wait_min', '5')) * 60))),
        ];
        if ($id) {
            DB::update('events', $data, 'id = ? AND station_id = ?', [$id, $sid]);
        } else {
            DB::insert('events', [...$data, 'station_id' => $sid]);
        }
        flash('success', "Evento “{$data['name']}” salvo. O estúdio aberto passa a usá-lo em até 1 minuto, sem precisar recarregar.");
        redirect('/eventos?grade=' . $set);
    }

    private function dt(string $date, string $time): ?string
    {
        $time = strlen($time) === 5 ? $time . ':00' : $time;
        $d = \DateTime::createFromFormat('Y-m-d H:i:s', $date . ' ' . $time);
        return $d && $d->format('Y-m-d H:i:s') === $date . ' ' . $time ? $d->format('Y-m-d H:i:s') : null;
    }

    public function bulk(): void
    {
        $sid = Auth::stationId();
        $set = (int) input('grade', 0);
        $action = (string) input('action', '');
        if ($action === 'delete_expired') {
            $n = DB::run('DELETE FROM events WHERE station_id = ? AND event_set_id = ? AND expires_at IS NOT NULL AND expires_at < ?', [$sid, $set, now()])->rowCount();
            flash('success', $n . ' evento(s) expirado(s) excluído(s).');
            redirect('/eventos?grade=' . $set);
        }
        $ids = array_slice(array_map('intval', (array) ($_POST['ids'] ?? [])), 0, 2000);
        if (!$ids) {
            flash('error', 'Selecione pelo menos um evento.');
            redirect('/eventos?grade=' . $set);
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        match ($action) {
            'delete' => DB::run("DELETE FROM events WHERE station_id = ? AND id IN ($in)", [$sid, ...$ids]),
            'enable' => DB::run("UPDATE events SET enabled = 1 WHERE station_id = ? AND id IN ($in)", [$sid, ...$ids]),
            'disable' => DB::run("UPDATE events SET enabled = 0 WHERE station_id = ? AND id IN ($in)", [$sid, ...$ids]),
            'copy' => $this->copy($sid, $ids, (int) input('target_set', 0)),
            default => abort(400),
        };
        $n = count($ids);
        if ($action === 'delete') {
            \App\Core\Audit::record('events.delete', 'set:' . $set, "$n evento(s)");
        }
        flash('success', match ($action) {
            'delete' => "$n evento(s) excluído(s).",
            'enable' => "$n evento(s) ativado(s). Eles voltam a tocar no próximo horário.",
            'disable' => "$n evento(s) desativado(s). Eles não tocam até você ativar de novo.",
            default => "$n evento(s) copiado(s) para a outra grade.",
        });
        redirect('/eventos?grade=' . $set);
    }

    private function copy(int $sid, array $ids, int $target): void
    {
        if (!DB::value('SELECT 1 FROM event_sets WHERE id = ? AND station_id = ?', [$target, $sid])) {
            abort(422, 'Grade de destino inválida.');
        }
        foreach ($ids as $id) {
            $e = DB::one('SELECT * FROM events WHERE id = ? AND station_id = ?', [$id, $sid]);
            if ($e) {
                unset($e['id'], $e['created_at']);
                DB::insert('events', [...$e, 'event_set_id' => $target]);
            }
        }
    }

    public function saveSet(): void
    {
        $sid = Auth::stationId();
        $name = mb_substr(trim((string) input('name', '')), 0, 120);
        if ($name === '') {
            flash('error', 'Informe o nome da grade.');
            back();
        }
        $id = (int) input('id', 0);
        if ($id) {
            DB::update('event_sets', ['name' => $name], 'id = ? AND station_id = ?', [$id, $sid]);
        } else {
            $id = DB::insert('event_sets', ['station_id' => $sid, 'name' => $name]);
        }
        redirect('/eventos?grade=' . $id);
    }

    public function activateSet(int $id): void
    {
        $sid = Auth::stationId();
        if (DB::value('SELECT 1 FROM event_sets WHERE id = ? AND station_id = ?', [$id, $sid])) {
            DB::update('stations', ['active_event_set_id' => $id], 'id = ?', [$sid]);
            \App\Core\Audit::record('eventset.activate', 'set:' . $id);
            flash('success', 'Esta grade está no ar. O estúdio aberto passa a usá-la em até 1 minuto.');
        }
        redirect('/eventos?grade=' . $id);
    }

    public function deleteSet(int $id): void
    {
        $sid = Auth::stationId();
        if ((int) station()['active_event_set_id'] === $id) {
            flash('error', 'Não é possível excluir a grade ativa. Ative outra antes.');
            redirect('/eventos?grade=' . $id);
        }
        DB::run('DELETE FROM event_sets WHERE id = ? AND station_id = ?', [$id, $sid]);
        \App\Core\Audit::record('eventset.delete', 'set:' . $id);
        flash('success', 'Grade excluída, com os eventos dela.');
        redirect('/eventos');
    }
}
