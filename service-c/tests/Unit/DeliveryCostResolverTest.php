<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTO\Food\DeliveryTierDto;
use App\Services\Food\DeliveryCostResolver;
use Tests\TestCase;

class DeliveryCostResolverTest extends TestCase
{
    public function test_is_applicable_returns_false_when_user_has_no_category(): void
    {
        $maxUser = \App\Models\MaxUser::query()->make([
            'max_user_id' => 12_001,
            'customer_category_id' => null,
        ]);

        $resolver = new DeliveryCostResolver();

        $this->assertFalse($resolver->isApplicable($maxUser));
    }

    public function test_is_applicable_returns_true_when_user_has_category(): void
    {
        $category = \App\Models\CustomerCategory::query()->make([
            'id' => 1,
            'name' => 'Standard',
        ]);

        $maxUser = \App\Models\MaxUser::query()->make([
            'max_user_id' => 12_002,
            'customer_category_id' => 1,
        ]);
        $maxUser->setRelation('customerCategory', $category);

        $resolver = new DeliveryCostResolver();

        $this->assertTrue($resolver->isApplicable($maxUser));
    }

    public function test_resolve_returns_zero_when_no_tiers_configured(): void
    {
        $resolver = new DeliveryCostResolver();

        $this->assertSame(0.0, $resolver->resolve(500.0, []));
    }

    public function test_resolve_picks_tier_by_descending_min_items_total(): void
    {
        $resolver = new DeliveryCostResolver();

        $tiers = [
            new DeliveryTierDto(minItemsTotal: 1000.0, deliveryCost: 0.0),
            new DeliveryTierDto(minItemsTotal: 0.0, deliveryCost: 200.0),
        ];

        $this->assertSame(200.0, $resolver->resolve(999.0, $tiers));
        $this->assertSame(0.0, $resolver->resolve(1000.0, $tiers));
        $this->assertSame(0.0, $resolver->resolve(1500.0, $tiers));
    }

    public function test_resolve_returns_zero_when_no_tier_threshold_matches(): void
    {
        $resolver = new DeliveryCostResolver();

        $tiers = [
            new DeliveryTierDto(minItemsTotal: 1000.0, deliveryCost: 0.0),
        ];

        $this->assertSame(0.0, $resolver->resolve(500.0, $tiers));
    }
}
