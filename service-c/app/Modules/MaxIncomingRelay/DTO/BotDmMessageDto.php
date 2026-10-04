<?php

declare(strict_types=1);

namespace App\Modules\MaxIncomingRelay\DTO;

use App\Modules\MaxIncomingRelay\Enums\BotDmAuthorType;

/**
 * Сообщение лички с ботом для API.
 */
readonly class BotDmMessageDto
{
    public function __construct(
        public int $id,
        public int $maxUserId,
        public int $senderMaxUserId,
        public ?string $senderFirstName,
        public ?string $senderLastName,
        public ?string $senderUsername,
        public BotDmAuthorType $authorType,
        public string $body,
        public ?int $chatId,
        public string $createdAt,
    ) {}

    /**
     * Собирает DTO из доменной проекции репозитория.
     */
    public static function fromRecord(BotDmMessageRecord $record): self
    {
        return new self(
            id: $record->id,
            maxUserId: $record->maxUserId,
            senderMaxUserId: $record->senderMaxUserId,
            senderFirstName: $record->senderFirstName,
            senderLastName: $record->senderLastName,
            senderUsername: $record->senderUsername,
            authorType: $record->authorType,
            body: $record->body,
            chatId: $record->chatId,
            createdAt: $record->createdAt,
        );
    }

    /**
     * Преобразует DTO сообщения в массив для JSON-ответа.
     *
     * @return array<string, int|string|null|array<string, string|null>>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'max_user_id' => $this->maxUserId,
            'sender_max_user_id' => $this->senderMaxUserId,
            'sender' => [
                'first_name' => $this->senderFirstName,
                'last_name' => $this->senderLastName,
                'username' => $this->senderUsername,
            ],
            'author_type' => $this->authorType->value,
            'body' => $this->body,
            'chat_id' => $this->chatId,
            'created_at' => $this->createdAt,
        ];
    }
}
