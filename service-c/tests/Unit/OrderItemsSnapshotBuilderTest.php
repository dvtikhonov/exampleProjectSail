<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\CartItem;
use App\Services\Food\OrderItemsSnapshotBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\Support\ResolvesDishImageUrl;
use Tests\TestCase;

class OrderItemsSnapshotBuilderTest extends TestCase
{
    use RefreshDatabase;
    use ResetsFoodDomainTables;
    use ResolvesDishImageUrl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
    }

    public function test_build_creates_snapshot_with_formatted_amounts_and_image_url(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish('Bistro', 'Steak', 700.0);

        $item = new CartItem([
            'dish_id' => $fixture['dish']->id,
            'quantity' => 2,
        ]);
        $item->setRelation('dish', $fixture['dish']);

        $snapshot = app(OrderItemsSnapshotBuilder::class)->build(collect([$item]));

        $this->assertSame(1400.0, $snapshot->itemsTotal);
        $this->assertCount(1, $snapshot->itemsSnapshot);
        $this->assertSame($fixture['dish']->id, $snapshot->itemsSnapshot[0]['dish_id']);
        $this->assertSame('Steak', $snapshot->itemsSnapshot[0]['dish_name']);
        $this->assertSame('700.00', $snapshot->itemsSnapshot[0]['unit_price']);
        $this->assertSame(2, $snapshot->itemsSnapshot[0]['quantity']);
        $this->assertSame('1400.00', $snapshot->itemsSnapshot[0]['line_total']);
        $this->assertSame(
            $this->expectedDishImageUrlForModel($fixture['dish']),
            $snapshot->itemsSnapshot[0]['image_url'],
        );
    }
}
