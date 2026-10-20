<?php

declare(strict_types=1);

namespace App\Modules\MaxIncomingRelay\Services;

use App\Contracts\Max\MaxMessengerNotificationSenderInterface;
use App\Contracts\Max\MaxUserIdentityRepositoryInterface;
use App\Contracts\Telegram\TelegramMessengerNotificationSenderInterface;
use App\DTO\Max\MessengerUserIdentity;
use App\Enums\MessengerChannel;
use App\Modules\MaxIncomingRelay\Contracts\BotDmChatServiceInterface;
use App\Modules\MaxIncomingRelay\Contracts\BotDmMessageRepositoryInterface;
use App\Modules\MaxIncomingRelay\DTO\BotDmMessageDto;
use App\Modules\MaxIncomingRelay\DTO\BotDmMessageRecord;
use App\Modules\MaxIncomingRelay\Enums\BotDmAuthorType;
use App\Modules\MaxIncomingRelay\Exceptions\BotDmDomainException;

/**
 * Сервис админской лички с пользователем MAX / Telegram через бота.
 */
final class BotDmChatService implements BotDmChatServiceInterface
{
    private const int MAX_BODY_LENGTH = 2000;

    private const int DEFAULT_LIST_LIMIT = 50;

    public function __construct(
        private readonly BotDmMessageRepositoryInterface $botDmMessageRepository,
        private readonly MaxUserIdentityRepositoryInterface $maxUserIdentityRepository,
        private readonly MaxMessengerNotificationSenderInterface $maxNotificationSender,
        private readonly TelegramMessengerNotificationSenderInterface $telegramNotificationSender,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function listMessages(
        int $maxUserId,
        ?int $afterId = null,
        int $limit = self::DEFAULT_LIST_LIMIT,
    ): array {
        $this->assertKnownUser($maxUserId);

        $messages = $this->botDmMessageRepository->listForUser(
            maxUserId: $maxUserId,
            afterId: $afterId,
            limit: $this->normalizeLimit($limit),
        );

        return array_map(
            static fn (BotDmMessageRecord $message): BotDmMessageDto => BotDmMessageDto::fromRecord($message),
            $messages,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function sendMessage(
        int $adminMaxUserId,
        int $maxUserId,
        string $body,
    ): BotDmMessageDto {
        $normalizedBody = $this->normalizeBody($body);
        $identity = $this->resolveKnownIdentity($maxUserId);

        $sent = $this->dispatchToMessenger(
            identity: $identity,
            text: $normalizedBody,
            adminMaxUserId: $adminMaxUserId,
        );

        if (! $sent) {
            $channelLabel = $identity->messenger === MessengerChannel::Telegram ? 'Telegram' : 'MAX';

            throw new BotDmDomainException(
                sprintf('Не удалось отправить сообщение в %s.', $channelLabel),
                502,
            );
        }

        $message = $this->botDmMessageRepository->create(
            maxUserId: $maxUserId,
            senderMaxUserId: $adminMaxUserId,
            authorType: BotDmAuthorType::Admin,
            body: $normalizedBody,
        );

        return BotDmMessageDto::fromRecord($message);
    }

    /**
     * Отправляет текст в канал пользователя (MAX Bot API или Telegram Bot API).
     */
    private function dispatchToMessenger(
        MessengerUserIdentity $identity,
        string $text,
        int $adminMaxUserId,
    ): bool {
        $logContext = [
            'max_user_id' => $identity->maxUserId,
            'admin_max_user_id' => $adminMaxUserId,
            'messenger' => $identity->messenger->value,
            'external_user_id' => $identity->externalUserId,
        ];

        if ($identity->messenger === MessengerChannel::Telegram) {
            return $this->telegramNotificationSender->send(
                text: $text,
                chatId: $identity->externalUserId,
                failureLogMessage: 'Telegram bot DM send failed',
                logContext: $logContext,
            );
        }

        return $this->maxNotificationSender->send(
            text: $text,
            userId: $identity->externalUserId,
            failureLogMessage: 'MAX bot DM send failed',
            logContext: $logContext,
        );
    }

    /**
     * Проверяет, что собеседник есть в max_users.
     *
     * @throws BotDmDomainException
     */
    private function assertKnownUser(int $maxUserId): void
    {
        $this->resolveKnownIdentity($maxUserId);
    }

    /**
     * Возвращает identity известного пользователя либо 404.
     *
     * @throws BotDmDomainException
     */
    private function resolveKnownIdentity(int $maxUserId): MessengerUserIdentity
    {
        $identity = $this->maxUserIdentityRepository->resolveMessengerIdentity($maxUserId);

        if ($identity === null) {
            throw new BotDmDomainException('Пользователь не найден.', 404);
        }

        return $identity;
    }

    /**
     * Нормализует и валидирует текст сообщения.
     *
     * @throws BotDmDomainException
     */
    private function normalizeBody(string $body): string
    {
        $normalized = trim($body);

        if ($normalized === '') {
            throw new BotDmDomainException('Текст сообщения обязателен.', 422);
        }

        if (mb_strlen($normalized) > self::MAX_BODY_LENGTH) {
            throw new BotDmDomainException(
                sprintf('Текст сообщения не должен превышать %d символов.', self::MAX_BODY_LENGTH),
                422,
            );
        }

        return $normalized;
    }

    /**
     * Нормализует лимит выборки сообщений.
     */
    private function normalizeLimit(int $limit): int
    {
        if ($limit < 1) {
            return self::DEFAULT_LIST_LIMIT;
        }

        return min($limit, 100);
    }
}
