<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Services\Items;
use App\Services\Stations;

final class ManageController
{
    /* ---------- Listas, rotações e sequências ---------- */

    public function playlists(): string
    {
        $sid = Auth::stationId();
        $rows = DB::all('SELECT id, name, kind, items, updated_at FROM playlists WHERE station_id = ? ORDER BY kind, name', [$sid]);
        foreach ($rows as &$r) {
            $items = json_decode((string) $r['items'], true) ?: [];
            $r['count'] = count($items);
            $r['duration_ms'] = array_sum(array_map(static fn ($i) => (int) ($i['duration_ms'] ?? 0), $items));
            unset($r['items']);
        }
        return view('manage/playlists', ['title' => 'Listas', 'playlists' => $rows]);
    }

    public function createPlaylist(): void
    {
        $sid = Auth::stationId();
        $kind = in_array(input('kind'), ['list', 'rotation', 'sequence'], true) ? (string) input('kind') : 'list';
        $name = mb_substr(trim((string) input('name', '')), 0, 120) ?: 'Nova lista';
        $id = DB::insert('playlists', ['station_id' => $sid, 'name' => $name, 'kind' => $kind, 'items' => '[]']);
        // Menos passos: já abre no estúdio, onde os itens são arrastados.
        redirect('/estudio#lista=' . $id);
    }

    public function updatePlaylist(int $id): void
    {
        $name = mb_substr(trim((string) input('name', '')), 0, 120);
        if ($name !== '') {
            DB::update('playlists', ['name' => $name], 'id = ? AND station_id = ?', [$id, Auth::stationId()]);
            flash('success', "Renomeada para “{$name}”.");
        }
        redirect('/listas#p' . $id);
    }

    public function deletePlaylist(int $id): void
    {
        $name = (string) DB::value('SELECT name FROM playlists WHERE id = ? AND station_id = ?', [$id, Auth::stationId()]);
        DB::run('DELETE FROM playlists WHERE id = ? AND station_id = ?', [$id, Auth::stationId()]);
        Audit::record('playlist.delete', 'playlist:' . $id, $name);
        flash('success', "“{$name}” foi excluída.");
        redirect('/listas');
    }

    /* ---------- Vinhetas (10 páginas × 9 botões) ---------- */

    public function jingles(): string
    {
        $sid = Auth::stationId();
        $page = max(0, min(9, (int) input('pagina', 0)));
        $row = DB::one('SELECT * FROM jingle_pages WHERE station_id = ? AND page_no = ?', [$sid, $page]);
        $buttons = array_fill(0, 9, null);
        foreach (json_decode((string) ($row['buttons'] ?? '[]'), true) ?: [] as $b) {
            if (isset($b['slot']) && $b['slot'] >= 0 && $b['slot'] < 9) {
                $buttons[(int) $b['slot']] = $b;
            }
        }
        return view('manage/jingles', [
            'title' => 'Vinhetas',
            'page' => $page,
            'pageName' => $row['name'] ?? 'Página ' . ($page + 1),
            'pages' => DB::all('SELECT page_no, name FROM jingle_pages WHERE station_id = ? ORDER BY page_no', [$sid]),
            'buttons' => $buttons,
            'media' => \App\Services\Media::pickerList($sid),
        ]);
    }

    public function saveJingles(): void
    {
        $sid = Auth::stationId();
        $page = max(0, min(9, (int) input('page', 0)));
        $buttons = [];
        for ($slot = 0; $slot < 9; $slot++) {
            $src = (string) ($_POST['source'][$slot] ?? '');
            $item = match (true) {
                ctype_digit($src) => Items::sanitizeOne(['type' => 'track', 'media_id' => (int) $src], $sid),
                in_array($src, ['time', 'temperature', 'humidity'], true) => Items::sanitizeOne(['type' => $src], $sid),
                default => null,
            };
            if (!$item) {
                continue;
            }
            $mode = in_array($_POST['mode'][$slot] ?? '', ['normal', 'loop', 'sweeper'], true) ? $_POST['mode'][$slot] : 'normal';
            $label = mb_substr(trim((string) ($_POST['label'][$slot] ?? '')), 0, 24) ?: mb_substr((string) $item['title'], 0, 24);
            $buttons[] = ['slot' => $slot, 'label' => $label, 'mode' => $mode, 'item' => $item];
        }
        $name = mb_substr(trim((string) input('name', '')), 0, 60) ?: 'Página ' . ($page + 1);
        DB::update('jingle_pages', ['name' => $name, 'buttons' => json_encode($buttons, JSON_UNESCAPED_UNICODE)], 'station_id = ? AND page_no = ?', [$sid, $page]);
        flash('success', "Página “{$name}” salva com " . count($buttons) . ' vinheta(s). O estúdio aberto recebe a mudança em até 1 minuto.', ['href' => '/estudio', 'label' => 'Abrir o estúdio']);
        redirect('/vinhetas?pagina=' . $page);
    }

    /* ---------- Sweepers (locução sobre a música) ---------- */

    public function sweepers(): string
    {
        $sid = Auth::stationId();
        $rows = DB::all('SELECT s.*, m.filename FROM sweepers s JOIN media m ON m.id = s.media_id WHERE s.station_id = ? ORDER BY m.filename, s.offset_s', [$sid]);
        foreach ($rows as &$r) {
            $r['item'] = json_decode((string) $r['item'], true);
        }
        return view('manage/sweepers', [
            'title' => 'Sweepers',
            'sweepers' => $rows,
            'media' => \App\Services\Media::pickerList($sid),
        ]);
    }

    public function addSweeper(): void
    {
        $sid = Auth::stationId();
        $target = (int) input('media_id', 0);
        $src = (string) input('source', '');
        $item = ctype_digit($src) ? Items::sanitizeOne(['type' => 'track', 'media_id' => (int) $src], $sid)
            : (in_array($src, ['time', 'temperature', 'humidity'], true) ? Items::sanitizeOne(['type' => $src], $sid) : null);
        if (!$item || !\App\Services\Media::readable($sid, $target)) {
            flash('error', 'Não deu para adicionar: escolha a música e o que vai tocar por cima.');
            redirect('/sweepers');
        }
        $offset = max(0, min(3600, (float) str_replace(',', '.', (string) input('offset_s', '0'))));
        DB::insert('sweepers', ['station_id' => $sid, 'media_id' => $target, 'item' => json_encode($item, JSON_UNESCAPED_UNICODE),
            'offset_s' => $offset]);
        flash('success', "“{$item['title']}” vai tocar por cima aos " . rtrim(rtrim(number_format($offset, 1, ',', ''), '0'), ',') . ' s. Funciona sempre que essa música tocar no estúdio.');
        redirect('/sweepers');
    }

    public function deleteSweepers(): void
    {
        $sid = Auth::stationId();
        if (input('all')) {
            DB::run('DELETE FROM sweepers WHERE station_id = ?', [$sid]);
        } else {
            foreach (array_map('intval', (array) ($_POST['ids'] ?? [])) as $id) {
                DB::run('DELETE FROM sweepers WHERE id = ? AND station_id = ?', [$id, $sid]);
            }
        }
        flash('success', 'Sweepers excluídos.');
        redirect('/sweepers');
    }

    /* ---------- Registro de execução (log browser) ---------- */

    public function log(): string
    {
        $sid = Auth::stationId();
        $mode = in_array(input('modo'), ['arquivo', 'erros', 'tudo'], true) ? (string) input('modo') : 'tudo';
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) input('de')) ? (string) input('de') : date('Y-m-d');
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) input('ate')) ? (string) input('ate') : date('Y-m-d');
        $q = trim((string) input('q', ''));
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }
        if ((strtotime($to) - strtotime($from)) > 366 * 86400) {
            $from = date('Y-m-d', strtotime($to . ' -366 days'));
            flash('info', 'O período máximo é de 1 ano. Ajustei a data inicial para ' . date('d/m/Y', strtotime($from)) . '.');
        }
        $where = ['station_id = ?', 'played_at >= ?', 'played_at <= ?'];
        $params = [$sid, $from . ' 00:00:00', $to . ' 23:59:59'];
        if ($mode === 'erros') {
            $where[] = "status = 'error'";
        }
        if ($mode === 'arquivo' && $q !== '') {
            $where[] = "title LIKE ? ESCAPE '!'";
            $params[] = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
        }
        $export = input('exportar');
        $rows = DB::all('SELECT played_at, source, kind, title, status, detail FROM play_log WHERE ' . implode(' AND ', $where) . ' ORDER BY played_at' . ($export ? '' : ' LIMIT 5000'), $params);
        if ($export === 'txt' || $export === 'csv') {
            $name = 'relatorio-' . station()['slug'] . '-' . $from . '_' . $to . '.' . $export;
            header('Content-Type: ' . ($export === 'csv' ? 'text/csv' : 'text/plain') . '; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $name . '"');
            $out = fopen('php://output', 'w');
            if ($export === 'csv') {
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, ['data_hora', 'origem', 'tipo', 'titulo', 'status', 'detalhe'], ';', '"', '');
                foreach ($rows as $r) {
                    // Evita fórmulas maliciosas ao abrir no Excel.
                    // Achado N1: neutraliza fórmulas, inclusive iniciadas por TAB ou CR.
                    fputcsv($out, array_map(static fn ($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : $v, array_values($r)), ';', '"', '');
                }
            } else {
                fwrite($out, 'Relatório de transmissão — ' . station()['name'] . " — $from a $to\r\n\r\n");
                foreach ($rows as $r) {
                    fwrite($out, $r['played_at'] . "\t" . $r['title'] . ($r['status'] === 'error' ? "\tERRO: " . $r['detail'] : '') . "\r\n");
                }
            }
            exit;
        }
        return view('manage/log', ['title' => 'Registro', 'rows' => $rows, 'mode' => $mode, 'from' => $from, 'to' => $to, 'q' => $q]);
    }

    /* ---------- Opções ---------- */

    public function options(): string
    {
        $st = station();
        return view('manage/options', ['title' => 'Opções', 'settings' => Stations::settings($st), 'station' => $st,
            'timezones' => \DateTimeZone::listIdentifiers()]);
    }

    public function saveOptions(): void
    {
        $sid = Auth::stationId();
        $post = $_POST;
        // Checkboxes desmarcados não chegam no POST.
        foreach (Stations::DEFAULTS as $k => $v) {
            if (is_bool($v) && !isset($post[$k])) {
                $post[$k] = false;
            }
        }
        if (($post['stream_pass'] ?? '') === '') {
            unset($post['stream_pass']); // campo em branco mantém a senha atual
        }
        if (isset($post['stream_url']) && $post['stream_url'] !== '' && !preg_match('#^wss?://[^\s"<>]+$#i', (string) $post['stream_url'])) {
            flash('error', 'Nada foi salvo: o endereço de transmissão precisa começar com wss://. Corrija em "Transmissão" e salve de novo.');
            redirect('/opcoes');
        }
        if (!station()['streaming']) {
            $post['stream_enabled'] = false;
        }
        Stations::saveSettings($sid, $post);
        $name = mb_substr(trim((string) input('station_name', '')), 0, 120);
        $tz = (string) input('timezone', '');
        $data = [];
        if ($name !== '') $data['name'] = $name;
        if (in_array($tz, \DateTimeZone::listIdentifiers(), true)) $data['timezone'] = $tz;
        if ($data) {
            DB::update('stations', $data, 'id = ?', [$sid]);
        }
        Audit::record('options.save', null, isset($post['stream_pass']) ? 'inclui senha de transmissão' : null);
        flash('success', 'Opções salvas. O estúdio aberto aplica as mudanças em até 1 minuto, sem precisar recarregar.', ['href' => '/estudio', 'label' => 'Abrir o estúdio']);
        redirect('/opcoes');
    }

    /* ---------- Usuários da rádio ---------- */

    public function users(): string
    {
        $sid = Auth::stationId();
        return view('manage/users', ['title' => 'Usuários', 'users' => DB::all('SELECT * FROM users WHERE station_id = ? ORDER BY name', [$sid]), 'max' => (int) station()['max_users']]);
    }

    public function saveUser(): void
    {
        $sid = Auth::stationId();
        $id = (int) input('id', 0);
        $role = in_array(input('role'), ['admin', 'operator'], true) ? (string) input('role') : 'operator';
        $pass = (string) ($_POST['password'] ?? '');
        if ($id) {
            $u = DB::one('SELECT * FROM users WHERE id = ? AND station_id = ?', [$id, $sid]);
            if (!$u) {
                abort(404);
            }
            // Achado F1: a credencial do dono só pode ser mudada por ele mesmo (ou pelo superadmin).
            $actor = user();
            if ($u['role'] === 'owner' && (int) $actor['id'] !== (int) $u['id'] && $actor['role'] !== 'superadmin') {
                abort(403, 'Só o próprio dono pode mudar a senha dele.');
            }
            if ((int) $u['id'] === (int) $actor['id'] && $pass !== '') {
                flash('error', 'Para trocar a sua própria senha, use Minha conta (lá pedimos a senha atual).');
                redirect('/conta');
            }
            $data = $u['role'] === 'owner' ? [] : ['role' => $role, 'active' => input('active') ? 1 : 0];
            if ($pass !== '') {
                if (strlen($pass) < 10) {
                    flash('error', 'Nada foi salvo: a nova senha precisa ter pelo menos 10 caracteres.');
                    redirect('/usuarios');
                }
                $data['password_hash'] = password_hash($pass, PASSWORD_DEFAULT);
            }
            if ($data) {
                DB::update('users', $data, 'id = ?', [$id]);
                Audit::record('user.update', 'user:' . $id, array_keys($data) === ['password_hash'] ? 'nova senha' : implode(',', array_map(fn ($k) => $k === 'password_hash' ? 'senha' : $k, array_keys($data))));
            }
            flash('success', "{$u['name']} foi atualizado(a)." . ($pass !== '' ? ' As sessões abertas dessa pessoa foram encerradas.' : ''));
            redirect('/usuarios');
        }
        $email = mb_strtolower(trim((string) input('email', '')));
        $name = mb_substr(trim((string) input('name', '')), 0, 120);
        $count = (int) DB::value('SELECT COUNT(*) FROM users WHERE station_id = ?', [$sid]);
        if ($count >= (int) station()['max_users']) {
            flash('error', 'Seu plano permite ' . station()['max_users'] . ' usuário(s). Para adicionar mais, fale com o suporte sobre outro plano.');
            redirect('/usuarios');
        }
        $errors = [];
        if ($name === '') $errors['name'] = 'Digite o nome da pessoa.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Digite um e-mail válido, como nome@dominio.com.br.';
        elseif (DB::value('SELECT 1 FROM users WHERE email = ?', [$email])) $errors['email'] = 'Este e-mail já tem conta no sistema. Use outro e-mail.';
        if (strlen($pass) < 10) $errors['password'] = 'Use pelo menos 10 caracteres, ou clique em "Gerar senha".';
        if ($errors) {
            field_errors($errors, $_POST);
            redirect('/usuarios');
        }
        $newId = DB::insert('users', ['station_id' => $sid, 'name' => $name, 'email' => $email, 'password_hash' => password_hash($pass, PASSWORD_DEFAULT), 'role' => $role]);
        Audit::record('user.create', 'user:' . $newId, $role);
        flash('success', "{$name} já pode entrar com o e-mail {$email}. Envie a senha por um canal seguro (não por e-mail aberto).");
        redirect('/usuarios');
    }

    public function deleteUser(int $id): void
    {
        $sid = Auth::stationId();
        if ($id === (int) user()['id']) {
            flash('error', 'Você não pode excluir a si mesmo.');
            redirect('/usuarios');
        }
        $name = (string) DB::value("SELECT name FROM users WHERE id = ? AND station_id = ? AND role <> 'owner'", [$id, $sid]);
        if ($name === '') {
            abort(404);
        }
        DB::run("DELETE FROM users WHERE id = ? AND station_id = ? AND role <> 'owner'", [$id, $sid]);
        Audit::record('user.delete', 'user:' . $id, $name);
        flash('success', "{$name} foi excluído(a) e perdeu o acesso na hora.");
        redirect('/usuarios');
    }

    /* ---------- Auditoria (quem fez o quê) ---------- */

    public function audit(): string
    {
        $rows = DB::all('SELECT a.*, u.name AS actor_name FROM audit_log a LEFT JOIN users u ON u.id = a.actor_id WHERE a.station_id = ? ORDER BY a.id DESC LIMIT 500', [Auth::stationId()]);
        return view('manage/audit', ['title' => 'Auditoria', 'rows' => $rows]);
    }
}
