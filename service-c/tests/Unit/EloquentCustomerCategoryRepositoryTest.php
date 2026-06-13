<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\Food\CustomerCategoryName;
use App\Models\CustomerCategory;
use App\Repositories\Food\EloquentCustomerCategoryRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloquentCustomerCategoryRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CustomerCategory::query()->delete();
    }

    public function test_find_or_create_default_category_id_creates_standard_category(): void
    {
        $repository = new EloquentCustomerCategoryRepository();

        $categoryId = $repository->findOrCreateDefaultCategoryId();

        $this->assertDatabaseHas('max_customer_categories', [
            'id' => $categoryId,
            'name' => CustomerCategoryName::Standard->value,
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    public function test_find_or_create_default_category_id_returns_existing_standard_category(): void
    {
        $repository = new EloquentCustomerCategoryRepository();

        $firstCategoryId = $repository->findOrCreateDefaultCategoryId();
        $categoryCountAfterFirstCall = CustomerCategory::query()->count();

        $this->assertSame($firstCategoryId, $repository->findOrCreateDefaultCategoryId());
        $this->assertSame($categoryCountAfterFirstCall, CustomerCategory::query()->count());
    }
}
