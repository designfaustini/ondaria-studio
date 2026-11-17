<?php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    private static ?array $user = null;
    private static ?array $station = null;
    private static bool $loaded = false;

    /** Impressão da senha atual: se a senha mudar, as sessões abertas antes deixam de valer (achado F5). */
    private static function pwv(string $hash): string
    {
        return substr(hash('sha256', $hash), 0, 32);
    }

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = Session::get('uid');
            if (is_int($id)) {
                $u = DB::one('SELECT id, station_id, name, email, role, active, password_hash, terms_version, terms_accepted_at FROM users WHERE id = ?', [$id]);
                if (!$u || !(int) $u['active'] || !hash_equals(self::pwv((string) $u['password_hash']), (string) Session::get('pwv', ''))) {
                    Session::forget('uid');
                    Session::forget('pwv');
                    $u = null;
                }
                if ($u) {
                    unset($u['password_hash']);
                }
                self::$user = $u;
            }
        }
        return self::$user;
    }

    /** Rádio ativa: a do usuário ou, para o superadmin, a que ele abriu pelo painel. */
    public static function station(): ?array
    {
        if (self::$station !== null) {
            return self::$station;
        }
        $u = self::user();
        if (!$u) {
            return null;
        }
        $sid = $u['role'] === 'superadmin' ? Session::get('as_station') : (int) $u['station_id'];
        if (!$sid) {
            return null;
        }
        self::$station = DB::one('SELECT s.*, p.name AS plan_name, p.storage_mb, p.max_users, p.max_aux_players, p.streaming
            FROM stations s JOIN plans p ON p.id = s.plan_id WHERE s.id = ?', [(int) $sid]);
        return self::$station;
    }

    public static function attempt(string $email, string $password): bool
    {
        $u = DB::one('SELECT id, password_hash, active FROM users WHERE email = ?', [mb_strtolower($email)]);
        // Mesmo custo de tempo quando o e-mail não existe, para não revelar contas.
        $hash = $u['password_hash'] ?? password_hash(random_bytes(8), PASSWORD_DEFAULT);
        if (!password_verify($password, $hash) || !$u || !(int) $u['active']) {
            Audit::access('login_falhou', $u ? (int) $u['id'] : null, $email);
            return false;
        }
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            DB::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$u['id']]);
        }
        self::login((int) $u['id']);
        Audit::access('login', (int) $u['id']);
        return true;
    }

    public static function login(int $userId): void
    {
        Session::regenerate();
        Session::set('uid', $userId);
        Session::set('pwv', self::pwv((string) DB::value('SELECT password_hash FROM users WHERE id = ?', [$userId])));
        DB::update('users', ['last_login_at' => now()], 'id = ?', [$userId]);
        self::$loaded = false;
        self::$station = null;
    }

    public static function logout(): void
    {
        if ($u = self::user()) {
            Audit::access('logout', (int) $u['id']);
        }
        Session::destroy();
        self::$user = null;
        self::$station = null;
    }

    public static function isManager(): bool
    {
        return in_array(self::user()['role'] ?? '', ['owner', 'admin', 'superadmin'], true);
    }

    public static function stationId(): int
    {
        $s = self::station();
        if (!$s) {
            abort(403);
        }
        return (int) $s['id'];
    }
}
