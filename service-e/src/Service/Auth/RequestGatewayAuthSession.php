<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Contract\Auth\GatewayAuthSessionInterface;
use App\DTO\Auth\GatewayUserDto;
use App\Security\GatewayAuth;
use Symfony\Component\HttpFoundation\RequestStack;

/** Сохраняет User в атрибуты текущего Symfony-запроса. */
class RequestGatewayAuthSession implements GatewayAuthSessionInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    /** {@inheritDoc} */
    public function login(GatewayUserDto $user): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return;
        }

        $request->attributes->set(GatewayAuth::USER_ATTRIBUTE, $user->user);
    }
}
