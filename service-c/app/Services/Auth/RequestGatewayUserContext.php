<?php

namespace App\Services\Auth;

use App\Contracts\Auth\GatewayUserContextInterface;
use Illuminate\Http\Request;

/**
 * Контекст текущего пользователя gateway из HTTP-запроса.
 */
class RequestGatewayUserContext implements GatewayUserContextInterface
{
    public function __construct(
        private readonly Request $request,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function currentUserId(): ?int
    {
        $userId = $this->request->user()?->id;

        return is_int($userId) ? $userId : null;
    }
}
