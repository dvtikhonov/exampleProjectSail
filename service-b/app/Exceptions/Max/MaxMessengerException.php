<?php

namespace App\Exceptions\Max;

use RuntimeException;

abstract class MaxMessengerException extends RuntimeException
{
    abstract public function userMessage(): string;
}
