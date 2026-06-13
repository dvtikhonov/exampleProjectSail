<?php

declare(strict_types=1);

namespace App\Repositories\Food;

use App\Contracts\Food\CustomerCategoryRepositoryInterface;
use App\Enums\Food\CustomerCategoryName;
use App\Models\CustomerCategory;

class EloquentCustomerCategoryRepository implements CustomerCategoryRepositoryInterface
{
    public function findOrCreateDefaultCategoryId(): int
    {
        return CustomerCategory::query()->firstOrCreate(
            ['name' => CustomerCategoryName::Standard->value],
            ['sort_order' => 1, 'is_active' => true],
        )->id;
    }
}
