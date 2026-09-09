<?php

declare(strict_types=1);

namespace App\Services\Max\UiStand;

use App\Contracts\Max\MaxWebhookStaleDevTunnelCleanerInterface;
use App\Contracts\Max\MaxWebhookSubscriptionClientInterface;
use App\Contracts\Shared\ApplicationConfigInterface;

/**
 * Удаление устаревших dev-туннелей из подписок MAX webhook.
 */
class MaxWebhookStaleDevTunnelCleaner implements MaxWebhookStaleDevTunnelCleanerInterface
{
    public function __construct(
        private readonly MaxWebhookSubscriptionClientInterface $subscriptionClient,
        private readonly ApplicationConfigInterface $config,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function unsubscribeStaleDevTunnels(string $configuredUrl): array
    {
        $configuredUrl = trim($configuredUrl);
        $removed = [];
        $preserved = [];

        foreach ($this->subscriptionClient->listSubscriptions() as $subscription) {
            $url = trim((string) ($subscription['url'] ?? ''));

            if ($url === '' || $url === $configuredUrl) {
                continue;
            }

            if (! $this->isRemovableDevTunnelUrl($url)) {
                $preserved[] = $url;

                continue;
            }

            $this->subscriptionClient->unsubscribe($url);
            $removed[] = $url;
        }

        return [
            'removed' => $removed,
            'preserved' => $preserved,
        ];
    }

    /**
     * Проверяет, является ли URL удаляемым dev-туннелем.
     */
    private function isRemovableDevTunnelUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower($host);

        foreach ($this->removableDevTunnelHostSuffixes() as $suffix) {
            if (str_ends_with($host, strtolower($suffix))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Возвращает суффиксы хостов удаляемых dev-туннелей.
     *
     * @return list<string>
     */
    private function removableDevTunnelHostSuffixes(): array
    {
        $suffixes = $this->config->get('max.webhook.clean_removable_host_suffixes', []);

        if (! is_array($suffixes)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $suffix): string => trim((string) $suffix),
            $suffixes,
        )));
    }
}
