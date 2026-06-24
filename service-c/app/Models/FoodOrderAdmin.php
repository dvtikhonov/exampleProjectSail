<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Food\FoodOrderAdminRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'max_user_id',
    'role',
    'is_active',
])]
/**
 * Роль администратора проверки заказов еды (таблица max_food_order_admins).
 */
class FoodOrderAdmin extends Model
{
    protected $table = 'max_food_order_admins';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_user_id' => 'integer',
            'role' => FoodOrderAdminRole::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<MaxUser, $this>
     */
    public function maxUser(): BelongsTo
    {
        return $this->belongsTo(MaxUser::class, 'max_user_id', 'max_user_id');
    }
}
