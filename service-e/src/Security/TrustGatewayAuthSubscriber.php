<?php

declare(strict_types=1);

namespace App\Security;

use App\Contract\Auth\GatewayAuthSessionInterface;
use App\Contract\Auth\GatewayUserResolverInterface;
use App\Response\GatewayUnauthorizedResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Проверяет заголовок X-User-Id для всех запросов к /api/*.
 * Аналог TrustGatewayAuth middleware в service-a.
 */
class TrustGatewayAuthSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly GatewayUserResolverInterface $userResolver,
        private readonly GatewayAuthSessionInterface $authSession,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (! str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $dto = $this->userResolver->resolveFromRequest($request);

        if ($dto === null) {
            $event->setResponse(GatewayUnauthorizedResponse::make());

            return;
        }

        $this->authSession->login($dto);
    }
}
