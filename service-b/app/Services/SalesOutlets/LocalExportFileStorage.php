<?php

namespace App\Services\SalesOutlets;

use App\Contracts\SalesOutlets\ExportFileStorageInterface;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LocalExportFileStorage implements ExportFileStorageInterface
{
    public function put(string $path, string $contents): void
    {
        Storage::disk('local')->put($path, $contents);
    }

    public function exists(string $path): bool
    {
        return Storage::disk('local')->exists($path);
    }

    public function download(string $path, string $name, array $headers): StreamedResponse
    {
        return Storage::disk('local')->download($path, $name, $headers);
    }
}
