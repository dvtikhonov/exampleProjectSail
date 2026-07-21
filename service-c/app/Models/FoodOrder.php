<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Food\OrderReviewStatus;
use App\Enums\Food\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'cart_id',
    'max_user_id',
    'is_manual',
    'created_by_max_user_id',
    'restaurant_id',
    'status',
    'address_review_status',
    'composition_review_status',
    'payment_review_status',
    'address_reviewed_by',
    'address_reviewed_at',
    'composition_reviewed_by',
    'composition_reviewed_at',
    'address_rejection_comment',
    'composition_rejection_comment',
    'payment_reviewed_by',
    'payment_reviewed_at',
    'payment_rejection_comment',
    'total',
    'delivery_address',
    'delivery_cost',
    'items_total',
    'items_snapshot',
])]
/**
 * Заказ еды пользователя MAX mini-app (таблица max_food_orders).
 */
class FoodOrder extends Model
{
    protected $table = 'max_food_orders';

    /**
     * Возвращает приведения атрибутов модели.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_user_id' => 'integer',
            'is_manual' => 'boolean',
            'created_by_max_user_id' => 'integer',
            'status' => OrderStatus::class,
            'address_review_status' => OrderReviewStatus::class,
            'composition_review_status' => OrderReviewStatus::class,
            'payment_review_status' => OrderReviewStatus::class,
            'address_reviewed_by' => 'integer',
            'address_reviewed_at' => 'datetime',
            'composition_reviewed_by' => 'integer',
            'composition_reviewed_at' => 'datetime',
            'payment_reviewed_by' => 'integer',
            'payment_reviewed_at' => 'datetime',
            'total' => 'decimal:2',
            'delivery_cost' => 'decimal:2',
            'items_total' => 'decimal:2',
            'items_snapshot' => 'array',
        ];
    }

    /**
     * Связь с корзиной заказа.
     *
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * Связь с заказчиком MAX.
     *
     * @return BelongsTo<MaxUser, $this>
     */
    public function maxUser(): BelongsTo
    {
        return $this->belongsTo(MaxUser::class, 'max_user_id', 'max_user_id');
    }

    /**
     * Связь с менеджером, оформившим ручной заказ.
     *
     * @return BelongsTo<MaxUser, $this>
     */
    public function createdByMaxUser(): BelongsTo
    {
        return $this->belongsTo(MaxUser::class, 'created_by_max_user_id', 'max_user_id');
    }

    /**
     * Связь с рестораном.
     *
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class)->withTrashed();
    }

    /**
     * Связь с администратором, проверившим адрес.
     *
     * @return BelongsTo<MaxUser, $this>
     */
    public function addressReviewedBy(): BelongsTo
    {
        return $this->belongsTo(MaxUser::class, 'address_reviewed_by', 'max_user_id');
    }

    /**
     * Связь с администратором, проверившим состав.
     *
     * @return BelongsTo<MaxUser, $this>
     */
    public function compositionReviewedBy(): BelongsTo
    {
        return $this->belongsTo(MaxUser::class, 'composition_reviewed_by', 'max_user_id');
    }

    /**
     * Связь с администратором, проверившим оплату.
     *
     * @return BelongsTo<MaxUser, $this>
     */
    public function paymentReviewedBy(): BelongsTo
    {
        return $this->belongsTo(MaxUser::class, 'payment_reviewed_by', 'max_user_id');
    }

    /**
     * Связь с сообщениями чата заказа.
     *
     * @return HasMany<FoodOrderMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(FoodOrderMessage::class, 'food_order_id');
    }

    /**
     * Заказ ожидает проверки состава (включая legacy-записи с not_applicable после миграции).
     */
    public function isInCompositionReviewQueue(): bool
    {
        if (in_array($this->status, [OrderStatus::Rejected, OrderStatus::Confirmed], true)) {
            return false;
        }

        return $this->composition_review_status === OrderReviewStatus::Pending
            || $this->composition_review_status === OrderReviewStatus::NotApplicable;
    }
}
