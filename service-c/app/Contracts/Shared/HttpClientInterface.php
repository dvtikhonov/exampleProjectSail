<?php

declare(strict_types=1);

namespace App\Contracts\Shared;

use App\DTO\Shared\HttpResponseDto;

/**
 * Порт HTTP-клиента без зависимости от Http facade / PendingRequest.
 */
interface HttpClientInterface
{
    /**
     * Выполняет HTTP-запрос.
     *
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>|null  $jsonBody  JSON-тело; null — без тела
     */
    public function request(
        string $method,
        string $url,
        array $headers = [],
        ?array $jsonBody = null,
        ?string $baseUrl = null,
        int $timeoutSeconds = 30,
    ): HttpResponseDto;
}
