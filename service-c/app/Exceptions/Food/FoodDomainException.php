<?php

declare(strict_types=1);

namespace App\Exceptions\Food;

use RuntimeException;

/**
 * Доменная ошибка модуля заказа еды с HTTP-кодом ответа.
 */
class FoodDomainException extends RuntimeException
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
