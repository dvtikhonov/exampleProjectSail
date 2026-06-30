<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Contract\Auth\GatewayUserContextInterface;
use App\Security\GatewayAuth;
use App\Entity\User;
use Symfony\Component\HttpFoundation\RequestStack;

/** Читает id пользователя из атрибутов текущего Symfony-запроса. */
class RequestGatewayUserContext implements GatewayUserContextInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {}

    public function currentUserId(): ?int
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return null;
        }

        $user = $request->attributes->get(GatewayAuth::USER_ATTRIBUTE);

        return $user instanceof User ? $user->getId() : null;
    }
}
