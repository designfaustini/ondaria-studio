<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('STORAGE_PATH', BASE_PATH . '/storage');

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . '/app/helpers.php';

App\Core\Env::load(BASE_PATH . '/.env');

date_default_timezone_set(env('APP_TIMEZONE', 'America/Sao_Paulo'));
mb_internal_encoding('UTF-8');

foreach (['logs', 'sessions', 'media', 'tmp'] as $dir) {
    if (!is_dir(STORAGE_PATH . '/' . $dir)) {
        mkdir(STORAGE_PATH . '/' . $dir, 0750, true);
    }
}

$debug = env('APP_DEBUG') === 'true' && env('APP_ENV') === 'local';
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');
error_reporting(E_ALL);

set_exception_handler(static function (Throwable $e) use ($debug): void {
    error_log((string) $e);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $e . PHP_EOL);
        exit(1);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (is_json_request()) {
        echo json_encode(['ok' => false, 'error' => $debug ? $e->getMessage() : 'Erro interno.']);
        return;
    }
    echo $debug ? '<pre>' . e((string) $e) . '</pre>' : App\Core\View::render('errors/error', ['code' => 500, 'message' => 'Erro interno. Tente novamente.'], 'public');
});
