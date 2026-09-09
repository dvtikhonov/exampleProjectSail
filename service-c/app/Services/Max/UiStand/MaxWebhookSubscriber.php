<?php

declare(strict_types=1);

namespace App\Services\Max\UiStand;

use App\Contracts\Max\MaxWebhookStaleDevTunnelCleanerInterface;
use App\Contracts\Max\MaxWebhookSubscriberInterface;
use App\Contracts\Max\MaxWebhookSubscriptionClientInterface;
use App\Contracts\Max\MaxWebhookUrlProbeInterface;

/**
 * Тонкий делегат к узким портам MAX webhook (facade-composition).
 */
class MaxWebhookSubscriber implements MaxWebhookSubscriberInterface
{
    public function __construct(
        private readonly MaxWebhookSubscriptionClientInterface $subscriptionClient,
        private readonly MaxWebhookUrlProbeInterface $urlProbe,
        private readonly MaxWebhookStaleDevTunnelCleanerInterface $staleDevTunnelCleaner,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function listSubscriptions(): array
    {
        return $this->subscriptionClient->listSubscriptions();
    }

    /**
     * {@inheritDoc}
     */
    public function probeWebhookUrl(): array
    {
        return $this->urlProbe->probeWebhookUrl();
    }

    /**
     * {@inheritDoc}
     */
    public function unsubscribe(string $url): void
    {
        $this->subscriptionClient->unsubscribe($url);
    }

    /**
     * {@inheritDoc}
     */
    public function unsubscribeStaleDevTunnels(string $configuredUrl): array
    {
        return $this->staleDevTunnelCleaner->unsubscribeStaleDevTunnels($configuredUrl);
    }

    /**
     * {@inheritDoc}
     */
    public function subscribe(): void
    {
        $this->subscriptionClient->subscribe();
    }
}
