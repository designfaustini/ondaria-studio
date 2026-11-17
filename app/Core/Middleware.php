<?php
declare(strict_types=1);

namespace App\Core;

final class Middleware
{
    public static function run(string $name): void
    {
        match ($name) {
            'auth' => self::auth(),
            'login' => Auth::user() ? null : redirect('/entrar'),
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
        // Termos mudaram desde o último aceite: pede o novo aceite antes de abrir as telas.
        // A API do estúdio continua funcionando para não derrubar a rádio no ar.
        if (Auth::user()['terms_version'] !== \App\Services\Legal::termsVersion() && !is_json_request() && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            Session::set('intended', $_SERVER['REQUEST_URI'] ?? '/');
            redirect('/aceitar-termos');
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
        // "closing" (encerramento pedido pelo dono) continua funcionando durante a carência.
        if ($s['status'] === 'suspended' && (Auth::user()['role'] ?? '') !== 'superadmin') {
            abort(403, 'Esta rádio está suspensa. Fale com o suporte.');
        }
    }
}
