<?php

namespace App\Services\Auth;

use App\Contracts\Auth\GatewayUserContextInterface;
use Illuminate\Http\Request;

class RequestGatewayUserContext implements GatewayUserContextInterface
{
    public function __construct(
        private readonly Request $request,
    ) {}

    public function currentUserId(): ?int
    {
        $userId = $this->request->user()?->id;

        return is_int($userId) ? $userId : null;
    }
}
