<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'cart_id',
    'dish_id',
    'quantity',
    'combo_ref',
    'combo_partner_dish_id',
])]
/**
 * Позиция корзины: блюдо и количество (таблица max_cart_items).
 *
 * Для комбо-пары оба поля combo_ref и combo_partner_dish_id заполнены вместе;
 * для обычной позиции — оба null.
 */
class CartItem extends Model
{
    protected $table = 'max_cart_items';

    /**
     * Возвращает приведения атрибутов модели.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'combo_partner_dish_id' => 'integer',
        ];
    }

    /**
     * Связь с корзиной.
     *
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * Связь с блюдом.
     *
     * @return BelongsTo<Dish, $this>
     */
    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    /**
     * Второе блюдо комбо-пары (взаимная ссылка на партнёра).
     *
     * @return BelongsTo<Dish, $this>
     */
    public function comboPartnerDish(): BelongsTo
    {
        return $this->belongsTo(Dish::class, 'combo_partner_dish_id');
    }
}
