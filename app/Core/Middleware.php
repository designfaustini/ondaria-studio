<?php
declare(strict_types=1);

namespace App\Core;

final class Middleware
{
    public static function run(string $name): void
    {
        match ($name) {
            'auth' => self::auth(),
            'guest' => Auth::user() ? redirect(Auth::user()['role'] === 'superadmin' && !Auth::station() ? '/admin' : '/estudio') : null,
            'station' => self::station(),
            'manager' => Auth::isManager() ? null : abort(403, 'Somente administradores da rádio podem acessar esta área.'),
            'super' => (Auth::user()['role'] ?? '') === 'superadmin' ? null : abort(403),
            'nocsrf' => null,
            default => throw new \LogicException('Middleware desconhecido: ' . $name),
        };
    }

    private static function auth(): void
    {
        if (!Auth::user()) {
            if (is_json_request()) {
                abort(401, 'Sessão encerrada. Entre novamente.');
            }
            Session::set('intended', $_SERVER['REQUEST_URI'] ?? '/');
            redirect('/entrar');
        }
    }

    private static function station(): void
    {
        $s = Auth::station();
        if (!$s) {
            if ((Auth::user()['role'] ?? '') === 'superadmin') {
                redirect('/admin');
            }
            abort(403);
        }
        if ($s['status'] !== 'active' && (Auth::user()['role'] ?? '') !== 'superadmin') {
            abort(403, 'Esta rádio está suspensa. Fale com o suporte.');
        }
    }
}
