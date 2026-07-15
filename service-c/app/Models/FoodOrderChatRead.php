<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'food_order_id',
    'reader_max_user_id',
    'last_read_message_id',
])]
/**
 * Позиция прочтения чата заказа для конкретного участника.
 */
class FoodOrderChatRead extends Model
{
    public const CREATED_AT = null;

    protected $table = 'max_food_order_chat_reads';

    /**
     * Возвращает приведения атрибутов модели.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'food_order_id' => 'integer',
            'reader_max_user_id' => 'integer',
            'last_read_message_id' => 'integer',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Связь с заказом.
     *
     * @return BelongsTo<FoodOrder, $this>
     */
    public function foodOrder(): BelongsTo
    {
        return $this->belongsTo(FoodOrder::class, 'food_order_id');
    }

    /**
     * Связь с читателем чата.
     *
     * @return BelongsTo<MaxUser, $this>
     */
    public function reader(): BelongsTo
    {
        return $this->belongsTo(MaxUser::class, 'reader_max_user_id', 'max_user_id');
    }

    /**
     * Связь с последним прочитанным сообщением.
     *
     * @return BelongsTo<FoodOrderMessage, $this>
     */
    public function lastReadMessage(): BelongsTo
    {
        return $this->belongsTo(FoodOrderMessage::class, 'last_read_message_id');
    }
}
