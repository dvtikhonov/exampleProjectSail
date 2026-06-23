<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'max_user_id',
    'first_name',
    'last_name',
    'username',
    'language_code',
    'photo_url',
    'customer_category_id',
    'delivery_address',
])]
/**
 * Пользователь MAX mini-app, аутентифицируемый через Sanctum.
 */
class MaxUser extends Authenticatable
{
    use HasApiTokens;

    protected $primaryKey = 'max_user_id';

    public $incrementing = false;

    protected $keyType = 'int';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_user_id' => 'integer',
            'customer_category_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CustomerCategory, $this>
     */
    public function customerCategory(): BelongsTo
    {
        return $this->belongsTo(CustomerCategory::class, 'customer_category_id');
    }

    /**
     * @return HasMany<Cart, $this>
     */
    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class, 'max_user_id', 'max_user_id');
    }

    /**
     * @return HasMany<FoodOrder, $this>
     */
    public function foodOrders(): HasMany
    {
        return $this->hasMany(FoodOrder::class, 'max_user_id', 'max_user_id');
    }
}
