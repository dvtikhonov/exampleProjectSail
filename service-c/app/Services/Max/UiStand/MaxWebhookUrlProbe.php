<?php

declare(strict_types=1);

namespace App\Services\Max\UiStand;

use App\Contracts\Max\MaxWebhookUrlProbeInterface;
use App\Contracts\Shared\ApplicationConfigInterface;
use App\Contracts\Shared\HttpClientInterface;
use Throwable;

/**
 * Проба доступности MAX_WEBHOOK_URL.
 */
class MaxWebhookUrlProbe implements MaxWebhookUrlProbeInterface
{
    public function __construct(
        private readonly ApplicationConfigInterface $config,
        private readonly HttpClientInterface $httpClient,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function probeWebhookUrl(): array
    {
        $url = trim((string) $this->config->get('max.webhook.url', ''));
        $secret = (string) $this->config->get('max.webhook.secret', '');

        if ($url === '') {
            return [
                'url' => '',
                'http_status' => null,
                'reachable' => false,
                'error' => 'MAX_WEBHOOK_URL не задан.',
            ];
        }

        if ($secret === '') {
            return [
                'url' => $url,
                'http_status' => null,
                'reachable' => false,
                'error' => 'MAX_WEBHOOK_SECRET не задан.',
            ];
        }

        try {
            $response = $this->httpClient->request(
                method: 'POST',
                url: $url,
                headers: [
                    'X-Max-Bot-Api-Secret' => $secret,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                jsonBody: [
                    'update_type' => 'probe',
                ],
                timeoutSeconds: 15,
            );

            return [
                'url' => $url,
                'http_status' => $response->status,
                'reachable' => $response->successful,
                'error' => $response->successful
                    ? null
                    : $this->formatProbeError($response->status, $response->body),
            ];
        } catch (Throwable $exception) {
            return [
                'url' => $url,
                'http_status' => null,
                'reachable' => false,
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * Форматирует ошибку probe-запроса webhook.
     */
    private function formatProbeError(int $status, string $body): string
    {
        if ($status === 530 && str_contains($body, '1033')) {
            return 'Cloudflare Error 1033: туннель зарегистрирован, но Cloudflare не доставляет запросы до cloudflared. '
                .'Типично для trycloudflare.com из РФ — используйте ./scripts/fxtun-tunnel.sh или cloudflared через VPN.';
        }

        return $body;
    }
}
