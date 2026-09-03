<?php

declare(strict_types=1);

namespace App\DTO\Shared;

/**
 * Ответ HTTP-клиента без зависимости от Illuminate Http Client.
 */
readonly class HttpResponseDto
{
    public function __construct(
        public int $status,
        public string $body,
        public bool $successful,
    ) {}

    /**
     * Декодирует JSON-тело.
     *
     * @return array<string, mixed>|list<mixed>|null
     */
    public function json(?string $key = null): mixed
    {
        if ($this->body === '') {
            return null;
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        if ($key === null) {
            return $decoded;
        }

        return $decoded[$key] ?? null;
    }
}
