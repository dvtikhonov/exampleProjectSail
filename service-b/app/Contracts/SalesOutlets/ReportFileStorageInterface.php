<?php

namespace App\Contracts\SalesOutlets;

use Symfony\Component\HttpFoundation\StreamedResponse;

interface ReportFileStorageInterface
{
    public function put(string $path, string $contents): void;

    public function exists(string $path): bool;

    /**
     * @param  array<string, string>  $headers
     */
    public function download(string $path, string $name, array $headers): StreamedResponse;
}
