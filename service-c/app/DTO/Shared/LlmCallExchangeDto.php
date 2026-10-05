<?php

declare(strict_types=1);

namespace App\DTO\Shared;

/**
 * Обмен с LLM для логирования: актор, запрос и ответ в текстовом виде.
 */
readonly class LlmCallExchangeDto
{
    /**
     * @param  array<string, string|int|float|bool|null>  $meta
     */
    public function __construct(
        public string $provider,
        public string $operation,
        public string $actor,
        public string $requestText,
        public ?string $responseText = null,
        public ?string $errorText = null,
        public array $meta = [],
    ) {}
}
