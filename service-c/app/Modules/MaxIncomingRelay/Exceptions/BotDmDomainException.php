<?php

declare(strict_types=1);

namespace App\Modules\MaxIncomingRelay\Exceptions;

use RuntimeException;

/**
 * Доменная ошибка лички с ботом с HTTP-кодом ответа.
 */
class BotDmDomainException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $statusCode = 422,
    ) {
        parent::__construct($message);
    }

    /**
     * Возвращает HTTP-код для ответа API.
     */
    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
