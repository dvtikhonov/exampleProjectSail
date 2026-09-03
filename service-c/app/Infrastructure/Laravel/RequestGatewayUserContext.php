<?php

namespace App\Infrastructure\Laravel;

use App\Contracts\Auth\GatewayUserContextInterface;
use Illuminate\Http\Request;

/**
 * Контекст текущего пользователя gateway из HTTP-запроса.
 *
 * Request читается при каждом вызове, а не из конструктора: иначе при
 * кэшировании зависимостей между HTTP-вызовами возвращается чужой user.
 */
class RequestGatewayUserContext implements GatewayUserContextInterface
{
    /**
     * {@inheritDoc}
     */
    public function currentUserId(): ?int
    {
        /** @var Request $request */
        $request = app(Request::class);
        $userId = $request->user()?->id;

        return is_int($userId) ? $userId : null;
    }
}
