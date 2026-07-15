<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'food_order_id',
    'sender_max_user_id',
    'body',
])]
/**
 * Сообщение в чате по заказу еды (таблица max_food_order_messages).
 */
class FoodOrderMessage extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'max_food_order_messages';

    /**
     * Возвращает приведения атрибутов модели.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'food_order_id' => 'integer',
            'sender_max_user_id' => 'integer',
            'created_at' => 'datetime',
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
     * Связь с отправителем сообщения.
     *
     * @return BelongsTo<MaxUser, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(MaxUser::class, 'sender_max_user_id', 'max_user_id');
    }
}
