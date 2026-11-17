<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\Session;
use App\Core\View;

function env(string $key, ?string $default = null): ?string
{
    return Env::get($key, $default);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = '/'): string
{
    $base = rtrim((string) env('APP_BASE_PATH', ''), '/');
    return $base . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = BASE_PATH . '/public/assets/' . ltrim($path, '/');
    $v = is_file($file) ? substr(md5((string) filemtime($file)), 0, 8) : '0';
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

function view(string $template, array $data = [], ?string $layout = 'app'): string
{
    return View::render($template, $data, $layout);
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

function back(): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $host = parse_url($ref, PHP_URL_HOST);
    redirect($ref !== '' && $host === ($_SERVER['HTTP_HOST'] ?? '') ? $ref : '/');
}

function abort(int $code, string $message = ''): never
{
    http_response_code($code);
    if (is_json_request()) {
        echo json_encode(['ok' => false, 'error' => $message ?: 'Erro ' . $code]);
        exit;
    }
    $messages = [403 => 'Acesso negado.', 404 => 'Página não encontrada.', 405 => 'Método não permitido.', 419 => 'Sessão expirada. Volte e recarregue a página.', 429 => 'Muitas tentativas. Aguarde alguns minutos.'];
    echo View::render('errors/error', ['code' => $code, 'message' => $message ?: ($messages[$code] ?? 'Erro.')], 'public');
    exit;
}

function is_json_request(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || str_starts_with(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', url('api/'));
}

function json_response(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/** Corpo JSON da requisição (API do estúdio). */
function json_input(): array
{
    static $data = null;
    if ($data === null) {
        $raw = file_get_contents('php://input') ?: '';
        $data = $raw !== '' ? json_decode($raw, true) : [];
        if (!is_array($data)) {
            json_response(['ok' => false, 'error' => 'JSON inválido.'], 400);
        }
    }
    return $data;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

/** Mensagem para a próxima tela; $next = ['href' => ..., 'label' => ...] mostra o próximo passo. */
function flash(string $type, string $message, ?array $next = null): void
{
    $f = Session::get('_flash_next', []);
    $f[] = [$type, $message, $next];
    Session::set('_flash_next', $f);
}

function flashes(): array
{
    return Session::get('_flash', []);
}

function old(string $key, string $default = ''): string
{
    $o = Session::get('_old', []);
    return (string) ($o[$key] ?? $default);
}

function remember_input(array $input): void
{
    unset($input['password'], $input['password_confirmation'], $input['_csrf']);
    Session::set('_old_next', $input);
}

/** Erros por campo para a próxima tela (mostrados ao lado do campo, mantendo o que foi digitado). */
function field_errors(array $errors, array $input = []): void
{
    Session::set('_errors_next', $errors);
    if ($input) {
        remember_input($input);
    }
}

function field_error(string $field): string
{
    $msg = Session::get('_errors', [])[$field] ?? null;
    return $msg ? '<p class="field-error" id="' . e($field) . '-err" role="alert">' . e($msg) . '</p>' : '<p class="field-error" id="' . e($field) . '-err" hidden></p>';
}

/** Atributos de acessibilidade do campo com erro. */
function field_attrs(string $field): string
{
    $has = isset(Session::get('_errors', [])[$field]);
    return ' aria-describedby="' . e($field) . '-err"' . ($has ? ' aria-invalid="true"' : '');
}

function user(): ?array
{
    return Auth::user();
}

function station(): ?array
{
    return Auth::station();
}

function input(string $key, mixed $default = null): mixed
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function format_duration(int $ms): string
{
    $s = intdiv(max(0, $ms), 1000);
    $h = intdiv($s, 3600);
    return $h > 0 ? sprintf('%d:%02d:%02d', $h, intdiv($s % 3600, 60), $s % 60) : sprintf('%d:%02d', intdiv($s, 60), $s % 60);
}

function format_bytes(int|float $bytes): string
{
    $u = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($u) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return number_format($bytes, $i > 1 ? 1 : 0, ',', '.') . ' ' . $u[$i];
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function slugify(string $text): string
{
    // Tabela própria: o iconv//TRANSLIT varia entre servidores (no Windows vira "r'adio").
    $t = strtr(mb_strtolower($text), [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n', 'ý' => 'y', 'ÿ' => 'y', 'ß' => 'ss', 'æ' => 'ae', 'ø' => 'o',
    ]);
    $t = preg_replace('/[^a-z0-9]+/', '-', $t) ?? '';
    return trim($t, '-') ?: 'radio';
}

function app_name(): string
{
    return (string) env('APP_NAME', 'Ondaria Studio');
}
