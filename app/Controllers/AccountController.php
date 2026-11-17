<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Services\Stations;

/** Minha conta: dados pessoais, senha, exportação e exclusão (direitos do titular, LGPD art. 18). */
final class AccountController
{
    private function me(): array
    {
        return DB::one('SELECT * FROM users WHERE id = ?', [user()['id']]);
    }

    private function graceDays(): int
    {
        return max(7, (int) env('CLOSING_GRACE_DAYS', '30'));
    }

    public function index(): string
    {
        return view('account/index', ['title' => 'Minha conta', 'me' => $this->me(), 'station' => station(), 'grace' => $this->graceDays()]);
    }

    public function profile(): void
    {
        $me = $this->me();
        $name = mb_substr(trim((string) input('name', '')), 0, 120);
        $email = mb_strtolower(trim((string) input('email', '')));
        $errors = [];
        if ($name === '') $errors['name'] = 'Digite seu nome.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Digite um e-mail válido, como nome@dominio.com.br.';
        elseif ($email !== $me['email'] && DB::value('SELECT 1 FROM users WHERE email = ? AND id <> ?', [$email, $me['id']])) $errors['email'] = 'Este e-mail já é usado por outra conta.';
        // Trocar o e-mail muda o login: pede a senha para confirmar que é a própria pessoa.
        if (!$errors && $email !== $me['email'] && !password_verify((string) ($_POST['current_profile'] ?? ''), (string) $me['password_hash'])) {
            $errors['current_profile'] = 'Para trocar o e-mail, digite sua senha atual.';
        }
        if ($errors) {
            field_errors($errors, $_POST);
            redirect('/conta');
        }
        DB::update('users', ['name' => $name, 'email' => $email], 'id = ?', [$me['id']]);
        Audit::record('account.profile', 'user:' . $me['id'], $email !== $me['email'] ? 'e-mail alterado' : 'nome alterado');
        flash('success', $email !== $me['email'] ? "Dados salvos. A partir de agora, entre com {$email}." : 'Dados salvos.');
        redirect('/conta');
    }

    public function password(): void
    {
        $me = $this->me();
        if (!password_verify((string) ($_POST['current'] ?? ''), (string) $me['password_hash'])) {
            field_errors(['current' => 'A senha atual não confere. Digite a senha que você usa para entrar.']);
            redirect('/conta');
        }
        $new = (string) ($_POST['password'] ?? '');
        if (strlen($new) < 10) {
            field_errors(['password' => 'Use pelo menos 10 caracteres na nova senha.']);
            redirect('/conta');
        }
        DB::update('users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT)], 'id = ?', [$me['id']]);
        Auth::login((int) $me['id']); // renova esta sessão; as outras sessões abertas são encerradas (achado F5)
        Audit::record('account.password', 'user:' . $me['id']);
        flash('success', 'Senha alterada. Outros computadores conectados com a senha antiga foram desconectados.');
        redirect('/conta');
    }

    /** Portabilidade: tudo o que guardamos sobre a pessoa e, para o dono, os dados da rádio. */
    public function export(): void
    {
        $me = $this->me();
        unset($me['password_hash']);
        $data = [
            'gerado_em' => date('c'),
            'sobre' => 'Exportação de dados pessoais (LGPD, art. 18). Senhas não são exportadas: guardamos apenas um hash irreversível.',
            'conta' => $me,
            'registros_de_acesso' => DB::all('SELECT event, ip, user_agent, created_at FROM access_log WHERE user_id = ? ORDER BY id DESC LIMIT 5000', [$me['id']]),
            'acoes_registradas' => DB::all('SELECT action, target, detail, ip, created_at FROM audit_log WHERE actor_id = ? ORDER BY id DESC LIMIT 5000', [$me['id']]),
        ];
        $st = station();
        if ($st && $me['role'] === 'owner') {
            $sid = (int) $st['id'];
            $settings = Stations::settings($st);
            unset($settings['stream_pass']);
            $data['radio'] = [
                'nome' => $st['name'], 'endereco_publico' => $st['slug'], 'fuso' => $st['timezone'], 'plano' => $st['plan_name'], 'criada_em' => $st['created_at'],
                'opcoes' => $settings,
                'usuarios' => DB::all('SELECT name, email, role, active, last_login_at, created_at FROM users WHERE station_id = ?', [$sid]),
                'pastas' => DB::all('SELECT id, parent_id, name, kind FROM folders WHERE station_id = ?', [$sid]),
                'arquivos' => DB::all('SELECT id, folder_id, filename, title, artist, duration_ms, size_bytes, created_at FROM media WHERE station_id = ?', [$sid]),
                'listas' => array_map(fn ($p) => [...$p, 'items' => json_decode((string) $p['items'], true)], DB::all('SELECT name, kind, items, updated_at FROM playlists WHERE station_id = ?', [$sid])),
                'grades' => DB::all('SELECT id, name FROM event_sets WHERE station_id = ?', [$sid]),
                'eventos' => array_map(fn ($e) => [...$e, 'item' => json_decode((string) $e['item'], true)], DB::all('SELECT event_set_id, name, enabled, start_at, expires_at, periodicity, hours_mask, weekdays_mask, item, wait_mode, priority, max_wait_s FROM events WHERE station_id = ?', [$sid])),
                'registro_de_execucao_12_meses' => DB::all('SELECT played_at, source, kind, title, status, detail FROM play_log WHERE station_id = ? AND played_at >= ? ORDER BY played_at', [$sid, date('Y-m-d H:i:s', strtotime('-12 months'))]),
                'observacao' => 'Os arquivos de áudio podem ser baixados um a um na Biblioteca (botão de ouvir > salvar).',
            ];
        }
        Audit::record('account.export', 'user:' . $me['id']);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="meus-dados-' . date('Y-m-d') . '.json"');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** Excluir a conta (operador/admin) ou encerrar a rádio inteira (dono), com carência. */
    public function delete(): void
    {
        $me = $this->me();
        if (!password_verify((string) ($_POST['current_delete'] ?? ''), (string) $me['password_hash'])) {
            field_errors(['current_delete' => 'Senha incorreta. Nada foi excluído.']);
            redirect('/conta#excluir');
        }
        if ($me['role'] === 'superadmin') {
            flash('error', 'A conta do administrador da plataforma não pode ser excluída por aqui.');
            redirect('/conta');
        }
        if ($me['role'] === 'owner') {
            $until = date('Y-m-d H:i:s', strtotime('+' . $this->graceDays() . ' days'));
            DB::update('stations', ['status' => 'closing', 'closing_at' => $until], 'id = ?', [$me['station_id']]);
            Audit::record('station.closing', 'station:' . $me['station_id'], 'apagamento em ' . $until);
            flash('warn', 'A rádio será apagada em ' . date('d/m/Y', strtotime($until)) . ', com todos os arquivos, listas, registros e usuários. Até lá você pode baixar seus dados ou cancelar o encerramento nesta página.');
            redirect('/conta');
        }
        Audit::record('account.delete', 'user:' . $me['id'], $me['email'], (int) $me['station_id']);
        DB::run('DELETE FROM users WHERE id = ?', [$me['id']]);
        Auth::logout();
        \App\Core\Session::start();
        flash('success', 'Sua conta foi excluída. Os registros de acesso ficam guardados por 6 meses, como exige o Marco Civil da Internet, e depois são apagados.');
        redirect('/');
    }

    public function cancelClosing(): void
    {
        $me = $this->me();
        if ($me['role'] !== 'owner') {
            abort(403);
        }
        DB::update('stations', ['status' => 'active', 'closing_at' => null], "id = ? AND status = 'closing'", [$me['station_id']]);
        Audit::record('station.closing_cancel', 'station:' . $me['station_id']);
        flash('success', 'Encerramento cancelado. A rádio continua funcionando normalmente.');
        redirect('/conta');
    }
}
