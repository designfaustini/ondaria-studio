<?php
declare(strict_types=1);

namespace App\Core;

final class Session
{
    private const IDLE_TIMEOUT = 43200;   // 12h: o estúdio fica aberto o dia inteiro
    private const ROTATE_EVERY = 1800;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $secure = str_starts_with((string) env('APP_URL'), 'https://');
        session_name($secure ? '__Host-ond_sess' : 'ond_sess');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) self::IDLE_TIMEOUT);
        ini_set('session.save_path', STORAGE_PATH . '/sessions');
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
        session_start();

        $now = time();
        if (isset($_SESSION['_last']) && $now - $_SESSION['_last'] > self::IDLE_TIMEOUT) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['_last'] = $now;
        if (!isset($_SESSION['_rotated']) || $now - $_SESSION['_rotated'] > self::ROTATE_EVERY) {
            session_regenerate_id(true);
            $_SESSION['_rotated'] = $now;
        }
        foreach (['_flash', '_old'] as $k) {
            $_SESSION[$k] = $_SESSION[$k . '_next'] ?? [];
            unset($_SESSION[$k . '_next']);
        }
    }

    /** Libera o arquivo de sessão para não bloquear requisições paralelas (áudio, API). */
    public static function release(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
        $_SESSION['_rotated'] = time();
        unset($_SESSION['_csrf']);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
        session_destroy();
    }
}
