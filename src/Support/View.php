<?php

declare(strict_types=1);

namespace App\Support;

class View
{
    private static string $templateDir = '';

    public static function setTemplateDir(string $dir): void
    {
        self::$templateDir = rtrim($dir, '/\\');
    }

    public static function render(string $template, array $data = []): void
    {
        $path = (self::$templateDir ?: dirname(__DIR__, 2) . '/templates')
            . '/' . ltrim($template, '/');

        if (!file_exists($path)) {
            throw new \RuntimeException("Template not found: {$path}");
        }

        extract($data, EXTR_SKIP);
        require $path;
    }
}
