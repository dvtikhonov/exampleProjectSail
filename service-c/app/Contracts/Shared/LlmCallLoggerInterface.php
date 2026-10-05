<?php

declare(strict_types=1);

namespace App\Contracts\Shared;

use App\DTO\Shared\LlmCallExchangeDto;

/**
 * Порт логирования обращений к LLM (запрос/ответ в текстовом виде).
 */
interface LlmCallLoggerInterface
{
    /**
     * Пишет обмен с LLM в канал логов (кто + request/response текстом).
     */
    public function logExchange(LlmCallExchangeDto $exchange): void;
}
