<?php

declare(strict_types=1);

namespace App\Contracts\Shared;

/**
 * Порт проверки окружения приложения без app()->environment().
 */
interface ApplicationEnvironmentInterface
{
    /**
     * True, если текущее окружение входит в список (например local, testing).
     *
     * @param  list<string>  $environments
     */
    public function is(array $environments): bool;
}
