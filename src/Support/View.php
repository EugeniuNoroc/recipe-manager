<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Минималистичный рендерер PHP-шаблонов.
 *
 * Загружает PHP-файл из директории шаблонов и передаёт переменные через extract().
 * Позволяет изменить директорию шаблонов через setTemplateDir() для тестов.
 *
 * @package App\Support
 */
class View
{
    /** @var string Директория шаблонов (по умолчанию /templates) */
    private static string $templateDir = '';

    /**
     * Задаёт директорию шаблонов (переопределяет путь по умолчанию).
     *
     * @param string $dir Абсолютный путь к директории шаблонов
     */
    public static function setTemplateDir(string $dir): void
    {
        self::$templateDir = rtrim($dir, '/\\');
    }

    /**
     * Рендерит шаблон с переданными переменными.
     *
     * Переменные из $data доступны в шаблоне напрямую (через extract).
     * Существующие переменные в scope не перезаписываются (EXTR_SKIP).
     *
     * @param  string               $template Имя файла шаблона (например 'header.php')
     * @param  array<string,mixed>  $data     Переменные для передачи в шаблон
     * @throws \RuntimeException              Если файл шаблона не найден
     */
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
