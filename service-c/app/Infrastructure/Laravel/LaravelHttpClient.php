<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Shared\HttpClientInterface;
use App\DTO\Shared\HttpResponseDto;
use Illuminate\Support\Facades\Http;

/**
 * Laravel-адаптер {@see HttpClientInterface} поверх Http facade.
 */
class LaravelHttpClient implements HttpClientInterface
{
    /**
     * {@inheritDoc}
     */
    public function request(
        string $method,
        string $url,
        array $headers = [],
        ?array $jsonBody = null,
        ?string $baseUrl = null,
        int $timeoutSeconds = 30,
    ): HttpResponseDto {
        $pending = Http::withHeaders($headers)
            ->acceptJson()
            ->asJson()
            ->timeout($timeoutSeconds);

        if ($baseUrl !== null && $baseUrl !== '') {
            $pending = $pending->baseUrl($baseUrl);
        }

        $response = $pending->send(
            strtoupper($method),
            $url,
            $jsonBody !== null ? ['json' => $jsonBody] : [],
        );

        return new HttpResponseDto(
            status: $response->status(),
            body: $response->body(),
            successful: $response->successful(),
        );
    }
}
