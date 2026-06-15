<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\DishImageDeliveryInterface;
use App\Models\Dish;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class DishImageDeliveryService implements DishImageDeliveryInterface
{
    public function deliver(Dish $dish): Response
    {
        $source = $dish->image_url;

        if ($source === null || $source === '') {
            abort(404);
        }

        if (str_starts_with($source, 'http://') || str_starts_with($source, 'https://')) {
            return $this->deliverRemote($source);
        }

        if (! Storage::disk('public')->exists($source)) {
            abort(404);
        }

        return Storage::disk('public')->response($source, headers: [
            'Cache-Control' => 'public, max-age=86400, immutable',
        ]);
    }

    private function deliverRemote(string $url): Response
    {
        $response = Http::timeout(15)
            ->connectTimeout(5)
            ->get($url);

        if (! $response->successful()) {
            abort(502, 'Image upstream unavailable.');
        }

        $contentType = $response->header('Content-Type');

        if ($contentType !== null && $contentType !== '' && ! str_starts_with($contentType, 'image/')) {
            abort(502, 'Invalid image content type.');
        }

        return response($response->body(), Response::HTTP_OK, [
            'Content-Type' => $contentType ?: 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400, immutable',
        ]);
    }
}
