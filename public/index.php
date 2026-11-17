<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Router;
use App\Core\Security;
use App\Core\Session;

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH) ?: '/';

// Servidor embutido do PHP (desenvolvimento): entrega arquivos estáticos direto.
if (PHP_SAPI === 'cli-server' && $path !== '/' && is_file(__DIR__ . $path)) {
    return false;
}

Security::headers();
if (!preg_match('#/np/[a-z0-9-]+\.(json|txt)$#', $path)) {
    Session::start();
}

$router = new Router();
require BASE_PATH . '/app/routes.php';
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $uri);
