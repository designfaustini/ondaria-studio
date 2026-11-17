<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];
    private array $groupMiddleware = [];
    private string $groupPrefix = '';

    public function get(string $path, array $handler, array $mw = []): void { $this->add('GET', $path, $handler, $mw); }
    public function post(string $path, array $handler, array $mw = []): void { $this->add('POST', $path, $handler, $mw); }

    public function group(string $prefix, array $middleware, callable $fn): void
    {
        [$p, $m] = [$this->groupPrefix, $this->groupMiddleware];
        $this->groupPrefix = $p . $prefix;
        $this->groupMiddleware = [...$m, ...$middleware];
        $fn($this);
        [$this->groupPrefix, $this->groupMiddleware] = [$p, $m];
    }

    private function add(string $method, string $path, array $handler, array $mw): void
    {
        $full = rtrim($this->groupPrefix . $path, '/') ?: '/';
        $regex = preg_replace_callback('/\{([a-z_]+)(?::([^}]+))?\}/', static function (array $m): string {
            $pattern = $m[2] ?? (str_ends_with($m[1], 'id') ? '\d+' : '[^/]+');
            return '(?P<' . $m[1] . '>' . $pattern . ')';
        }, $full);
        $this->routes[] = [$method, '#^' . $regex . '$#', $handler, [...$this->groupMiddleware, ...$mw]];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = rawurldecode(parse_url($uri, PHP_URL_PATH) ?: '/');
        $base = rtrim((string) env('APP_BASE_PATH', ''), '/');
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base)) ?: '/';
        }
        $path = $path !== '/' ? rtrim($path, '/') : '/';
        $allowed = false;
        foreach ($this->routes as [$m, $regex, $handler, $mw]) {
            if (!preg_match($regex, $path, $params)) {
                continue;
            }
            if ($m !== $method && !($method === 'HEAD' && $m === 'GET')) {
                $allowed = true;
                continue;
            }
            $params = array_filter($params, 'is_string', ARRAY_FILTER_USE_KEY);
            foreach ($mw as $middleware) {
                Middleware::run($middleware);
            }
            if ($method === 'POST' && !in_array('nocsrf', $mw, true)) {
                Csrf::verify();
            }
            [$class, $action] = $handler;
            foreach ($params as $k => $v) {
                if (str_ends_with($k, 'id')) {
                    $params[$k] = (int) $v;
                }
            }
            $out = (new $class())->$action(...$params);
            if (is_string($out)) {
                echo $out;
            }
            return;
        }
        abort($allowed ? 405 : 404);
    }
}
