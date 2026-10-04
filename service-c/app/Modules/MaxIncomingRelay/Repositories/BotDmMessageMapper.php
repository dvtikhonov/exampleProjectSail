<?php

declare(strict_types=1);

namespace App\Modules\MaxIncomingRelay\Repositories;

use App\Modules\MaxIncomingRelay\DTO\BotDmMessageRecord;
use App\Modules\MaxIncomingRelay\Enums\BotDmAuthorType;
use App\Modules\MaxIncomingRelay\Models\MaxBotDirectMessage;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Преобразование между Eloquent-сообщением лички с ботом и доменным Record.
 */
class BotDmMessageMapper
{
    /**
     * Преобразует модель сообщения в доменную проекцию.
     */
    public function toRecord(MaxBotDirectMessage $model): BotDmMessageRecord
    {
        $authorType = $model->author_type instanceof BotDmAuthorType
            ? $model->author_type
            : BotDmAuthorType::from((string) $model->author_type);

        return new BotDmMessageRecord(
            id: (int) $model->id,
            maxUserId: (int) $model->max_user_id,
            senderMaxUserId: (int) $model->sender_max_user_id,
            authorType: $authorType,
            body: (string) $model->body,
            chatId: $model->chat_id !== null ? (int) $model->chat_id : null,
            createdAt: $this->formatDateTime($model->created_at) ?? (new DateTimeImmutable)->format(DateTimeInterface::ATOM),
            senderFirstName: $model->relationLoaded('sender') ? $model->sender?->first_name : null,
            senderLastName: $model->relationLoaded('sender') ? $model->sender?->last_name : null,
            senderUsername: $model->relationLoaded('sender') ? $model->sender?->username : null,
        );
    }

    private function formatDateTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        return (string) $value;
    }
}
