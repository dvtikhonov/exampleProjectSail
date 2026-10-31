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
        bool $followRedirects = true,
    ): HttpResponseDto {
        $pending = Http::withHeaders($headers)
            ->acceptJson()
            ->asJson()
            ->timeout($timeoutSeconds);

        if (! $followRedirects) {
            $pending = $pending->withoutRedirecting();
        }

        if ($baseUrl !== null && $baseUrl !== '') {
            $pending = $pending->baseUrl($baseUrl);
        }

        if (is_string($baseUrl) && str_contains($baseUrl, 'api.telegram.org')) {
            // AAAA api.telegram.org из контейнера не соединяется: дефолтные 10 с curl съедает IPv6 и до IPv4 не доходит.
            $pending = $pending->withOptions([
                'connect_timeout' => 5,
                'curl' => [
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ],
            ]);
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
