<?php
declare(strict_types=1);

namespace App\Core;

final class Security
{
    public static function headers(): void
    {
        header_remove('X-Powered-By');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        header('Permissions-Policy: camera=(), geolocation=(self), microphone=(self), speaker-selection=(self)');
        // Rádios online (media-src) e servidores de transmissão (connect-src wss:) ficam em outros domínios.
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; "
            . "media-src 'self' blob: https: http:; connect-src 'self' https: wss: ws:; worker-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'");
        if (str_starts_with((string) env('APP_URL'), 'https://')) {
            header('Strict-Transport-Security: max-age=31536000');
        }
    }

    /** Limita tentativas (login, cadastro). Retorna false se o limite foi atingido. */
    public static function throttle(string $key, int $max, int $windowSeconds): bool
    {
        $since = date('Y-m-d H:i:s', time() - $windowSeconds);
        DB::run('DELETE FROM login_attempts WHERE attempted_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
        $count = (int) DB::value('SELECT COUNT(*) FROM login_attempts WHERE k = ? AND attempted_at >= ?', [$key, $since]);
        if ($count >= $max) {
            return false;
        }
        DB::insert('login_attempts', ['k' => $key, 'attempted_at' => now()]);
        return true;
    }

    public static function clientIp(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }
}
