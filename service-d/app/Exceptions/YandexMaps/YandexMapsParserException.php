<?php

declare(strict_types=1);

namespace App\Exceptions\YandexMaps;

use RuntimeException;

class YandexMapsParserException extends RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
