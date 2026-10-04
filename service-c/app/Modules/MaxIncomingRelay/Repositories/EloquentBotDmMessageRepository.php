<?php

declare(strict_types=1);

namespace App\Modules\MaxIncomingRelay\Repositories;

use App\Modules\MaxIncomingRelay\Contracts\BotDmMessageRepositoryInterface;
use App\Modules\MaxIncomingRelay\DTO\BotDmMessageRecord;
use App\Modules\MaxIncomingRelay\Enums\BotDmAuthorType;
use App\Modules\MaxIncomingRelay\Models\MaxBotDirectMessage;

/**
 * Eloquent-реализация репозитория сообщений лички с ботом.
 */
final class EloquentBotDmMessageRepository implements BotDmMessageRepositoryInterface
{
    public function __construct(
        private readonly BotDmMessageMapper $botDmMessageMapper,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function create(
        int $maxUserId,
        int $senderMaxUserId,
        BotDmAuthorType $authorType,
        string $body,
        ?int $chatId = null,
    ): BotDmMessageRecord {
        $message = MaxBotDirectMessage::query()->create([
            'max_user_id' => $maxUserId,
            'sender_max_user_id' => $senderMaxUserId,
            'author_type' => $authorType,
            'body' => $body,
            'chat_id' => $chatId,
        ]);

        $message->loadMissing('sender');

        return $this->botDmMessageMapper->toRecord($message);
    }

    /**
     * {@inheritDoc}
     */
    public function listForUser(int $maxUserId, ?int $afterId = null, int $limit = 50): array
    {
        $query = MaxBotDirectMessage::query()
            ->with('sender')
            ->where('max_user_id', $maxUserId)
            ->orderBy('id');

        if ($afterId !== null) {
            $query->where('id', '>', $afterId);
        }

        return $query
            ->limit($limit)
            ->get()
            ->map(fn (MaxBotDirectMessage $message): BotDmMessageRecord => $this->botDmMessageMapper->toRecord($message))
            ->all();
    }

    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?BotDmMessageRecord
    {
        $message = MaxBotDirectMessage::query()
            ->with('sender')
            ->find($id);

        return $message !== null ? $this->botDmMessageMapper->toRecord($message) : null;
    }
}
