<?php

declare(strict_types=1);

namespace App\Exceptions\Max;

use RuntimeException;

class MaxWebAppInitDataException extends RuntimeException
{
    public static function invalid(string $reason): self
    {
        return new self($reason);
    }

    public static function expired(int $authDate, int $maxAgeSeconds): self
    {
        return new self(sprintf(
            'initData auth_date %d is older than %d seconds.',
            $authDate,
            $maxAgeSeconds,
        ));
    }
}
