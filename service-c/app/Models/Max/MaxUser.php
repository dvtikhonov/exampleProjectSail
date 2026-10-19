<?php

declare(strict_types=1);

namespace App\Models\Max;

use App\Enums\MessengerChannel;
use App\Models\Food\Cart;
use App\Models\Food\CustomerCategory;
use App\Models\Food\FoodOrder;
use App\Models\Food\FoodOrderAdmin;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'max_user_id',
    'messenger',
    'external_user_id',
    'first_name',
    'last_name',
    'username',
    'language_code',
    'photo_url',
    'customer_category_id',
    'delivery_address',
])]
/**
 * Пользователь MAX / Telegram mini-app, аутентифицируемый через Sanctum.
 */
class MaxUser extends Authenticatable
{
    use HasApiTokens;

    protected $primaryKey = 'max_user_id';

    public $incrementing = false;

    protected $keyType = 'int';

    /**
     * Подставляет messenger / external_user_id, если при создании не заданы явно.
     */
    protected static function booted(): void
    {
        static::creating(function (MaxUser $user): void {
            $messengerValue = $user->messenger;
            if ($messengerValue === null || $messengerValue === '') {
                $user->messenger = MessengerChannel::Max;
                $messengerValue = MessengerChannel::Max;
            }

            $channel = $messengerValue instanceof MessengerChannel
                ? $messengerValue
                : MessengerChannel::from((string) $messengerValue);

            if ($user->external_user_id === null) {
                $user->external_user_id = $channel->externalUserIdFromInternal((int) $user->max_user_id);
            }
        });
    }

    /**
     * Возвращает приведения атрибутов модели.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_user_id' => 'integer',
            'messenger' => MessengerChannel::class,
            'external_user_id' => 'integer',
            'customer_category_id' => 'integer',
            'ai_access_until' => 'datetime',
        ];
    }

    /**
     * Связь с категорией клиента.
     *
     * @return BelongsTo<CustomerCategory, $this>
     */
    public function customerCategory(): BelongsTo
    {
        return $this->belongsTo(CustomerCategory::class, 'customer_category_id')->withTrashed();
    }

    /**
     * Связь с корзинами пользователя.
     *
     * @return HasMany<Cart, $this>
     */
    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class, 'max_user_id', 'max_user_id');
    }

    /**
     * Связь с заказами пользователя.
     *
     * @return HasMany<FoodOrder, $this>
     */
    public function foodOrders(): HasMany
    {
        return $this->hasMany(FoodOrder::class, 'max_user_id', 'max_user_id');
    }

    /**
     * Связь с ролями администратора заказов.
     *
     * @return HasMany<FoodOrderAdmin, $this>
     */
    public function adminRoles(): HasMany
    {
        return $this->hasMany(FoodOrderAdmin::class, 'max_user_id', 'max_user_id');
    }
}
