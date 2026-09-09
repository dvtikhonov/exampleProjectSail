<?php

declare(strict_types=1);

namespace App\Services\Max\UiStand;

use App\Contracts\Max\MaxWebhookSubscriptionClientInterface;
use App\Contracts\Shared\ApplicationConfigInterface;
use App\Contracts\Shared\HttpClientInterface;
use App\DTO\Shared\HttpResponseDto;
use App\Enums\Max\MaxWebhookUpdateType;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Shared\MaxMessenger\Exceptions\MaxMessengerAuthException;
use Shared\MaxMessenger\Exceptions\MaxMessengerRequestException;

/**
 * Platform HTTP-клиент подписок MAX webhook.
 */
class MaxWebhookSubscriptionClient implements MaxWebhookSubscriptionClientInterface
{
    private const BASE_URL = 'https://platform-api.max.ru';

    private const SUBSCRIPTIONS_ENDPOINT = '/subscriptions';

    public function __construct(
        private readonly ApplicationConfigInterface $config,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function listSubscriptions(): array
    {
        $token = $this->botAccessToken();

        $response = $this->platformRequest('GET', self::SUBSCRIPTIONS_ENDPOINT, $token);

        if ($response->status === 401) {
            throw new MaxMessengerAuthException;
        }

        if (! $response->successful) {
            throw new MaxMessengerRequestException(
                safeUserMessage: $this->safeErrorMessageForStatus($response->status),
            );
        }

        $subscriptions = $response->json('subscriptions');

        return is_array($subscriptions) ? $subscriptions : [];
    }

    /**
     * {@inheritDoc}
     */
    public function unsubscribe(string $url): void
    {
        $url = trim($url);

        if ($url === '') {
            throw new RuntimeException('URL подписки MAX webhook не задан.');
        }

        $token = $this->botAccessToken();

        $response = $this->platformRequest(
            'DELETE',
            self::SUBSCRIPTIONS_ENDPOINT.'?url='.rawurlencode($url),
            $token,
        );

        if ($response->successful) {
            $this->logger->info('MAX webhook subscription removed.', [
                'endpoint' => self::SUBSCRIPTIONS_ENDPOINT,
                'http_status' => $response->status,
                'webhook_url' => $url,
            ]);

            return;
        }

        $status = $response->status;

        $this->logger->warning('MAX webhook unsubscribe failed.', [
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
     * {@inheritDoc}
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

        $response = $this->platformRequest(
            'POST',
            self::SUBSCRIPTIONS_ENDPOINT,
            $token,
            [
                'url' => $url,
                'secret' => $secret,
                'update_types' => MaxWebhookUpdateType::values(),
            ],
        );

        if ($response->successful) {
            $this->logger->info('MAX webhook subscription registered.', [
                'endpoint' => self::SUBSCRIPTIONS_ENDPOINT,
                'http_status' => $response->status,
                'webhook_url' => $url,
                'update_types' => MaxWebhookUpdateType::values(),
            ]);

            return;
        }

        $status = $response->status;

        $this->logger->warning('MAX webhook subscription failed.', [
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
     * Выполняет запрос к platform-api.max.ru.
     *
     * @param  array<string, mixed>|null  $jsonBody
     */
    private function platformRequest(
        string $method,
        string $path,
        string $token,
        ?array $jsonBody = null,
    ): HttpResponseDto {
        return $this->httpClient->request(
            method: $method,
            url: $path,
            headers: [
                'Authorization' => $token,
            ],
            jsonBody: $jsonBody,
            baseUrl: self::BASE_URL,
        );
    }

    /**
     * Возвращает access-токен бота MAX.
     */
    private function botAccessToken(): string
    {
        $token = (string) $this->config->get('max.bot_access_token', '');

        if ($token === '') {
            throw new MaxMessengerAuthException;
        }

        return $token;
    }

    /**
     * Возвращает безопасное сообщение об ошибке по HTTP-статусу.
     */
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
