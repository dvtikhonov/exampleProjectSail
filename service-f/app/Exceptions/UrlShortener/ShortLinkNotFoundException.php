<?php

declare(strict_types=1);

namespace App\Exceptions\UrlShortener;

use RuntimeException;

/**
 * Короткая ссылка с указанным кодом не найдена.
 */
class ShortLinkNotFoundException extends RuntimeException
{
    /** @param string $code публичный код или строковое представление id */
    public function __construct(string $code)
    {
        parent::__construct("Short link with code [{$code}] was not found.");
    }
}
