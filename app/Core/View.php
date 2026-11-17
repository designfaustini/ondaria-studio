<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $template, array $data = [], ?string $layout = 'app'): string
    {
        $content = self::partial($template, $data);
        return $layout === null ? $content : self::partial('layouts/' . $layout, [...$data, 'content' => $content]);
    }

    public static function partial(string $template, array $data = []): string
    {
        if (!preg_match('#^[a-z0-9/_-]+$#', $template)) {
            throw new \InvalidArgumentException('Template inválido');
        }
        $file = BASE_PATH . '/views/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('View não encontrada: ' . $template);
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
