<?php

declare(strict_types=1);

namespace App\Repositories\Food\Delivery;

use App\Contracts\Food\Delivery\CustomerCategoryRepositoryInterface;
use App\DTO\Food\Delivery\CustomerCategoryDto;
use App\Enums\Food\Delivery\CustomerCategoryName;
use App\Models\Food\CustomerCategory;
use App\Models\Max\MaxUser;

/**
 * Eloquent-реализация репозитория категорий клиентов.
 */
class EloquentCustomerCategoryRepository implements CustomerCategoryRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function findOrCreateDefaultCategoryId(): int
    {
        return CustomerCategory::query()->firstOrCreate(
            ['name' => CustomerCategoryName::Standard->value],
            ['sort_order' => 1, 'is_active' => true],
        )->id;
    }

    /**
     * {@inheritDoc}
     */
    public function findCategoryForMaxUserId(int $maxUserId): ?CustomerCategoryDto
    {
        $user = MaxUser::query()
            ->with('customerCategory')
            ->where('max_user_id', $maxUserId)
            ->first();

        if ($user === null || $user->customer_category_id === null || $user->customerCategory === null) {
            return null;
        }

        return new CustomerCategoryDto(
            id: (int) $user->customerCategory->id,
            name: (string) $user->customerCategory->name,
        );
    }
}
