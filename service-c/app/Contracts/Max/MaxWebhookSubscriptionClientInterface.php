<?php

declare(strict_types=1);

namespace App\Contracts\Max;

use RuntimeException;
use Shared\MaxMessenger\Exceptions\MaxMessengerAuthException;
use Shared\MaxMessenger\Exceptions\MaxMessengerRequestException;

/**
 * Platform HTTP-клиент подписок MAX webhook (list / subscribe / unsubscribe).
 */
interface MaxWebhookSubscriptionClientInterface
{
    /**
     * Возвращает список активных webhook-подписок бота.
     *
     * @return list<array<string, mixed>>
     *
     * @throws MaxMessengerAuthException
     * @throws MaxMessengerRequestException
     */
    public function listSubscriptions(): array;

    /**
     * Удаляет webhook-подписку по URL.
     *
     * @throws RuntimeException
     * @throws MaxMessengerAuthException
     * @throws MaxMessengerRequestException
     */
    public function unsubscribe(string $url): void;

    /**
     * Регистрирует webhook-подписку с текущими настройками.
     *
     * @throws RuntimeException
     * @throws MaxMessengerAuthException
     * @throws MaxMessengerRequestException
     */
    public function subscribe(): void;
}
