<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Auth\GatewayUserContextInterface;
use App\Contracts\Shared\CurrentHttpRequestInterface;

/**
 * Контекст текущего пользователя gateway из HTTP-запроса.
 *
 * User читается при каждом вызове через {@see CurrentHttpRequestInterface},
 * а не из конструкторного Request: иначе при кэшировании зависимостей между
 * HTTP-вызовами возвращается чужой user.
 */
class RequestGatewayUserContext implements GatewayUserContextInterface
{
    public function __construct(
        private readonly CurrentHttpRequestInterface $currentHttpRequest,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function currentUserId(): ?int
    {
        $user = $this->currentHttpRequest->user();
        $userId = is_object($user) ? ($user->id ?? null) : null;

        return is_int($userId) ? $userId : null;
    }
}
