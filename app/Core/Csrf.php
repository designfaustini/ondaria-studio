<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        $t = Session::get('_csrf');
        if (!is_string($t) || strlen($t) !== 64) {
            $t = bin2hex(random_bytes(32));
            Session::set('_csrf', $t);
        }
        return $t;
    }

    public static function verify(): void
    {
        $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $valid = is_string($sent) && hash_equals(self::token(), $sent);

        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        if ($valid && $origin !== null && $origin !== 'null') {
            $valid = parse_url($origin, PHP_URL_HOST) === parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
        }
        if (!$valid) {
            abort(419, 'Sessão expirada. Recarregue a página.');
        }
    }
}
