<?php

namespace App\Services\SalesOutlets;

use App\Contracts\SalesOutlets\ReportFileStorageInterface;
use Illuminate\Filesystem\FilesystemAdapter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LocalReportFileStorage implements ReportFileStorageInterface
{
    public function __construct(
        private readonly FilesystemAdapter $filesystem,
    ) {}

    public function put(string $path, string $contents): void
    {
        $this->filesystem->put($path, $contents);
    }

    public function exists(string $path): bool
    {
        return $this->filesystem->exists($path);
    }

    public function download(string $path, string $name, array $headers): StreamedResponse
    {
        return $this->filesystem->download($path, $name, $headers);
    }
}
