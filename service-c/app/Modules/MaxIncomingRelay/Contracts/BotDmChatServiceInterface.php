<?php

declare(strict_types=1);

namespace App\Modules\MaxIncomingRelay\Contracts;

use App\Modules\MaxIncomingRelay\DTO\BotDmMessageDto;
use App\Modules\MaxIncomingRelay\Exceptions\BotDmDomainException;

/**
 * Чтение и отправка сообщений лички пользователя с ботом (admin Bot DM).
 */
interface BotDmChatServiceInterface
{
    /**
     * Возвращает ленту сообщений лички с пользователем.
     *
     * @return list<BotDmMessageDto>
     *
     * @throws BotDmDomainException
     */
    public function listMessages(
        int $maxUserId,
        ?int $afterId = null,
        int $limit = 50,
    ): array;

    /**
     * Отправляет текст пользователю в MAX и сохраняет сообщение админа в историю.
     *
     * @throws BotDmDomainException
     */
    public function sendMessage(
        int $adminMaxUserId,
        int $maxUserId,
        string $body,
    ): BotDmMessageDto;
}
