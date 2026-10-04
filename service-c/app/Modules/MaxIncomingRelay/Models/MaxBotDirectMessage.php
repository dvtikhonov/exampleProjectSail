<?php

declare(strict_types=1);

namespace App\Modules\MaxIncomingRelay\Models;

use App\Models\Max\MaxUser;
use App\Modules\MaxIncomingRelay\Enums\BotDmAuthorType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'max_user_id',
    'sender_max_user_id',
    'author_type',
    'body',
    'chat_id',
])]
/**
 * Сообщение лички пользователя с ботом (таблица max_bot_direct_messages).
 */
class MaxBotDirectMessage extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'max_bot_direct_messages';

    /**
     * Возвращает приведения атрибутов модели.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_user_id' => 'integer',
            'sender_max_user_id' => 'integer',
            'author_type' => BotDmAuthorType::class,
            'chat_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Собеседник (пользователь, с которым ведётся переписка).
     *
     * @return BelongsTo<MaxUser, $this>
     */
    public function peer(): BelongsTo
    {
        return $this->belongsTo(MaxUser::class, 'max_user_id', 'max_user_id');
    }

    /**
     * Автор сообщения.
     *
     * @return BelongsTo<MaxUser, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(MaxUser::class, 'sender_max_user_id', 'max_user_id');
    }
}
