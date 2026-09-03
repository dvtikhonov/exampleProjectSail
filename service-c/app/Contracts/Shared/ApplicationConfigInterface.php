<?php

declare(strict_types=1);

namespace App\Contracts\Shared;

/**
 * Порт чтения конфигурации приложения без прямой зависимости от Config Repository фреймворка.
 */
interface ApplicationConfigInterface
{
    /**
     * Возвращает значение конфигурации по ключу (dot-notation).
     */
    public function get(string $key, mixed $default = null): mixed;
}
