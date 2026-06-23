<?php

declare(strict_types=1);

namespace App\Exceptions\Max;

use RuntimeException;

/**
 * Ошибка валидации initData MAX WebApp.
 */
class MaxWebAppInitDataException extends RuntimeException
{
    /**
     * Создаёт исключение с произвольной причиной.
     */
    public static function invalid(string $reason): self
    {
        return new self($reason);
    }

    /**
     * Создаёт исключение при истечении срока действия auth_date.
     */
    public static function expired(int $authDate, int $maxAgeSeconds): self
    {
        return new self(sprintf(
            'initData auth_date %d is older than %d seconds.',
            $authDate,
            $maxAgeSeconds,
        ));
    }
}
