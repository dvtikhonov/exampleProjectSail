<?php

declare(strict_types=1);

namespace App\Modules\MaxIncomingRelay\Services;

use App\Contracts\Max\MaxMessengerNotificationSenderInterface;
use App\Contracts\Max\MaxUserIdentityRepositoryInterface;
use App\Modules\MaxIncomingRelay\Contracts\BotDmChatServiceInterface;
use App\Modules\MaxIncomingRelay\Contracts\BotDmMessageRepositoryInterface;
use App\Modules\MaxIncomingRelay\DTO\BotDmMessageDto;
use App\Modules\MaxIncomingRelay\DTO\BotDmMessageRecord;
use App\Modules\MaxIncomingRelay\Enums\BotDmAuthorType;
use App\Modules\MaxIncomingRelay\Exceptions\BotDmDomainException;

/**
 * Сервис админской лички с пользователем MAX через бота.
 */
final class BotDmChatService implements BotDmChatServiceInterface
{
    private const int MAX_BODY_LENGTH = 2000;

    private const int DEFAULT_LIST_LIMIT = 50;

    public function __construct(
        private readonly BotDmMessageRepositoryInterface $botDmMessageRepository,
        private readonly MaxUserIdentityRepositoryInterface $maxUserIdentityRepository,
        private readonly MaxMessengerNotificationSenderInterface $notificationSender,
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
        $this->assertKnownUser($maxUserId);

        $sent = $this->notificationSender->send(
            text: $normalizedBody,
            userId: $maxUserId,
            failureLogMessage: 'MAX bot DM send failed',
            logContext: [
                'max_user_id' => $maxUserId,
                'admin_max_user_id' => $adminMaxUserId,
            ],
        );

        if (! $sent) {
            throw new BotDmDomainException('Не удалось отправить сообщение в MAX.', 502);
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
     * Проверяет, что собеседник есть в max_users.
     *
     * @throws BotDmDomainException
     */
    private function assertKnownUser(int $maxUserId): void
    {
        if ($this->maxUserIdentityRepository->findByMaxUserId($maxUserId) === null) {
            throw new BotDmDomainException('Пользователь не найден.', 404);
        }
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
