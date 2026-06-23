<?php

declare(strict_types=1);

namespace App\Services\Max\UiStand;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Shared\MaxMessenger\Exceptions\MaxMessengerAuthException;
use Shared\MaxMessenger\Exceptions\MaxMessengerRequestException;
use Throwable;

/**
 * Управление подписками MAX webhook через platform API.
 */
class MaxWebhookSubscriber
{
    private const BASE_URL = 'https://platform-api.max.ru';

    private const SUBSCRIPTIONS_ENDPOINT = '/subscriptions';

    /**
     * @var list<string>
     */
    private const UPDATE_TYPES = [
        'message_callback',
        'bot_started',
    ];

    public function __construct(
        private readonly Repository $config,
    ) {}

    /**
     * Возвращает список активных webhook-подписок бота.
     *
     * @return list<array<string, mixed>>
     *
     * @throws MaxMessengerAuthException
     * @throws MaxMessengerRequestException
     */
    public function listSubscriptions(): array
    {
        $token = $this->botAccessToken();

        $response = $this->httpClient($token)->get(self::SUBSCRIPTIONS_ENDPOINT);

        if ($response->status() === 401) {
            throw new MaxMessengerAuthException;
        }

        if (! $response->successful()) {
            throw new MaxMessengerRequestException(
                safeUserMessage: $this->safeErrorMessageForStatus($response->status()),
            );
        }

        $subscriptions = $response->json('subscriptions');

        return is_array($subscriptions) ? $subscriptions : [];
    }

    /**
     * Проверяет доступность настроенного MAX_WEBHOOK_URL.
     *
     * @return array{url: string, http_status: int|null, reachable: bool, error: string|null}
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
            $response = Http::withHeaders([
                'X-Max-Bot-Api-Secret' => $secret,
            ])
                ->acceptJson()
                ->asJson()
                ->timeout(15)
                ->post($url, [
                    'update_type' => 'probe',
                ]);

            $body = $response->body();

            return [
                'url' => $url,
                'http_status' => $response->status(),
                'reachable' => $response->successful(),
                'error' => $response->successful() ? null : $this->formatProbeError($response->status(), $body),
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
     * Удаляет webhook-подписку по URL.
     *
     * @throws RuntimeException
     * @throws MaxMessengerAuthException
     * @throws MaxMessengerRequestException
     */
    public function unsubscribe(string $url): void
    {
        $url = trim($url);

        if ($url === '') {
            throw new RuntimeException('URL подписки MAX webhook не задан.');
        }

        $token = $this->botAccessToken();

        $response = $this->httpClient($token)->delete(
            self::SUBSCRIPTIONS_ENDPOINT.'?url='.rawurlencode($url),
        );

        if ($response->successful()) {
            Log::info('MAX webhook subscription removed.', [
                'endpoint' => self::SUBSCRIPTIONS_ENDPOINT,
                'http_status' => $response->status(),
                'webhook_url' => $url,
            ]);

            return;
        }

        $status = $response->status();

        Log::warning('MAX webhook unsubscribe failed.', [
            'endpoint' => self::SUBSCRIPTIONS_ENDPOINT,
            'http_status' => $status,
            'webhook_url' => $url,
        ]);

        if ($status === 401) {
            throw new MaxMessengerAuthException;
        }

        throw new MaxMessengerRequestException(
            safeUserMessage: $this->safeErrorMessageForStatus($status),
        );
    }

    /**
     * Удаляет устаревшие dev-туннели, сохраняя текущий URL.
     *
     * @return array{removed: list<string>, preserved: list<string>}
     */
    public function unsubscribeStaleDevTunnels(string $configuredUrl): array
    {
        $configuredUrl = trim($configuredUrl);
        $removed = [];
        $preserved = [];

        foreach ($this->listSubscriptions() as $subscription) {
            $url = trim((string) ($subscription['url'] ?? ''));

            if ($url === '' || $url === $configuredUrl) {
                continue;
            }

            if (! $this->isRemovableDevTunnelUrl($url)) {
                $preserved[] = $url;

                continue;
            }

            $this->unsubscribe($url);
            $removed[] = $url;
        }

        return [
            'removed' => $removed,
            'preserved' => $preserved,
        ];
    }

    /**
     * Регистрирует webhook-подписку с текущими настройками.
     *
     * @throws RuntimeException
     * @throws MaxMessengerAuthException
     * @throws MaxMessengerRequestException
     */
    public function subscribe(): void
    {
        $url = trim((string) $this->config->get('max.webhook.url', ''));
        $secret = (string) $this->config->get('max.webhook.secret', '');

        if ($url === '') {
            throw new RuntimeException('MAX_WEBHOOK_URL не задан в конфигурации.');
        }

        if ($secret === '') {
            throw new RuntimeException('MAX_WEBHOOK_SECRET не задан в конфигурации.');
        }

        if (strlen($secret) < 5) {
            throw new RuntimeException('MAX_WEBHOOK_SECRET должен содержать минимум 5 символов.');
        }

        if (! str_starts_with(strtolower($url), 'https://')) {
            throw new RuntimeException(
                'MAX_WEBHOOK_URL должен начинаться с https:// (MAX принимает webhook только по HTTPS:443).',
            );
        }

        if (! preg_match('/^[a-zA-Z0-9_-]+$/', $secret)) {
            throw new RuntimeException(
                'MAX_WEBHOOK_SECRET может содержать только латинские буквы, цифры, _ и - (5–256 символов).',
            );
        }

        $token = $this->botAccessToken();

        $response = $this->httpClient($token)->post(self::SUBSCRIPTIONS_ENDPOINT, [
            'url' => $url,
            'secret' => $secret,
            'update_types' => self::UPDATE_TYPES,
        ]);

        if ($response->successful()) {
            Log::info('MAX webhook subscription registered.', [
                'endpoint' => self::SUBSCRIPTIONS_ENDPOINT,
                'http_status' => $response->status(),
                'webhook_url' => $url,
                'update_types' => self::UPDATE_TYPES,
            ]);

            return;
        }

        $status = $response->status();

        Log::warning('MAX webhook subscription failed.', [
            'endpoint' => self::SUBSCRIPTIONS_ENDPOINT,
            'http_status' => $status,
            'webhook_url' => $url,
        ]);

        if ($status === 401) {
            throw new MaxMessengerAuthException;
        }

        throw new MaxMessengerRequestException(
            safeUserMessage: $this->safeErrorMessageForStatus($status),
        );
    }

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

    private function botAccessToken(): string
    {
        $token = (string) $this->config->get('max.bot_access_token', '');

        if ($token === '') {
            throw new MaxMessengerAuthException;
        }

        return $token;
    }

    private function httpClient(string $token): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->withHeaders([
                'Authorization' => $token,
            ])
            ->acceptJson()
            ->asJson();
    }

    private function formatProbeError(int $status, string $body): string
    {
        if ($status === 530 && str_contains($body, '1033')) {
            return 'Cloudflare Error 1033: туннель зарегистрирован, но Cloudflare не доставляет запросы до cloudflared. '
                .'Типично для trycloudflare.com из РФ — используйте ./scripts/fxtun-tunnel.sh или cloudflared через VPN.';
        }

        return $body;
    }

    private function safeErrorMessageForStatus(int $status): string
    {
        return match ($status) {
            400 => 'Некорректный запрос подписки MAX webhook. Проверьте MAX_WEBHOOK_URL и MAX_WEBHOOK_SECRET.',
            404 => 'Подписка MAX webhook не найдена.',
            405 => 'Операция подписки MAX webhook не поддерживается.',
            default => 'Не удалось зарегистрировать MAX webhook. Обратитесь к администратору.',
        };
    }
}
