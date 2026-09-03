<?php

declare(strict_types=1);

namespace App\Contracts\Max;

use App\DTO\Food\Shared\MaxUserIdentity;
use App\DTO\Max\MaxUserRecord;
use RuntimeException;

/**
 * Разрешение аутентифицированного пользователя MAX из текущего HTTP-контекста.
 */
interface AuthenticatedMaxUserResolverInterface
{
    /**
     * Возвращает доменную идентичность текущего пользователя MAX.
     *
     * @throws RuntimeException если пользователь не аутентифицирован как MaxUser
     */
    public function identity(): MaxUserIdentity;

    /**
     * Возвращает доменную проекцию текущего пользователя MAX.
     *
     * @throws RuntimeException если пользователь не аутентифицирован как MaxUser
     */
    public function record(): MaxUserRecord;
}
