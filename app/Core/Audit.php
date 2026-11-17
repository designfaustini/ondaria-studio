<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Trilhas de registro:
 *  - audit_log: quem mudou o quê na rádio (governança; visível ao dono em /auditoria).
 *  - access_log: acessos à aplicação com IP, data e hora (Marco Civil da Internet, art. 15: 6 meses).
 */
final class Audit
{
    public static function record(string $action, ?string $target = null, ?string $detail = null, ?int $stationId = null): void
    {
        try {
            $u = Auth::user();
            DB::insert('audit_log', [
                'station_id' => $stationId ?? (Auth::station()['id'] ?? null),
                'actor_id' => $u['id'] ?? null,
                'actor_role' => $u['role'] ?? null,
                'action' => mb_substr($action, 0, 40),
                'target' => $target !== null ? mb_substr($target, 0, 120) : null,
                'detail' => $detail !== null ? mb_substr($detail, 0, 255) : null,
                'ip' => Security::clientIp(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            error_log('audit: ' . $e->getMessage()); // nunca derruba a ação principal
        }
    }

    public static function access(string $event, ?int $userId, ?string $email = null): void
    {
        try {
            DB::insert('access_log', [
                'user_id' => $userId,
                'email_hash' => $email !== null ? hash('sha256', mb_strtolower($email)) : null,
                'event' => mb_substr($event, 0, 30),
                'ip' => Security::clientIp(),
                'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            error_log('access_log: ' . $e->getMessage());
        }
    }
}
