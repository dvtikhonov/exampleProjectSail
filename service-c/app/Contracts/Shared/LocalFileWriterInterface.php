<?php

declare(strict_types=1);

namespace App\Contracts\Shared;

/**
 * Порт записи локальных файлов (не Storage disk) без Filesystem фреймворка.
 */
interface LocalFileWriterInterface
{
    /**
     * Создаёт директорию рекурсивно, если её нет.
     */
    public function ensureDirectory(string $directory): void;

    /**
     * Записывает содержимое в абсолютный или относительный путь ФС.
     */
    public function put(string $path, string $contents): void;
}
