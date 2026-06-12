<?php

namespace Shared\MaxMessenger\Exceptions;

use RuntimeException;

abstract class MaxMessengerException extends RuntimeException
{
    abstract public function userMessage(): string;
}
