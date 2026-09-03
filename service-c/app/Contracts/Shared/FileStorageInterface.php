<?php

declare(strict_types=1);

namespace App\Contracts\Shared;

/**
 * Порт файлового хранилища без зависимости от Storage facade.
 */
interface FileStorageInterface
{
    /**
     * Создаёт директорию на диске, если её ещё нет.
     */
    public function makeDirectory(string $path, string $disk = 'public'): bool;

    /**
     * Записывает содержимое по относительному пути.
     *
     * @return bool false при ошибке записи
     */
    public function put(string $path, string $contents, string $disk = 'public'): bool;

    /**
     * Копирует локальный файл в директорию диска под указанным именем.
     *
     * @return string|false Относительный путь или false при ошибке
     */
    public function putFileAs(
        string $directory,
        string $localPath,
        string $filename,
        string $disk = 'public',
    ): string|false;

    /**
     * Проверяет существование файла на диске.
     */
    public function exists(string $path, string $disk = 'public'): bool;

    /**
     * Удаляет файл с диска.
     */
    public function delete(string $path, string $disk = 'public'): bool;

    /**
     * Абсолютный путь в ФС для относительного пути на диске.
     */
    public function path(string $path, string $disk = 'public'): string;
}
