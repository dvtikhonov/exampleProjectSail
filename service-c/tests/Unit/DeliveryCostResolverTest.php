<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTO\Food\Delivery\DeliveryTierDto;
use App\Services\Food\Delivery\DeliveryCostResolver;
use Tests\TestCase;

class DeliveryCostResolverTest extends TestCase
{
    /** isApplicable возвращает false, если категория не задана. */
    public function test_is_applicable_returns_false_when_user_has_no_category(): void
    {
        $resolver = new DeliveryCostResolver;

        $this->assertFalse($resolver->isApplicable(null));
    }

    /** isApplicable возвращает true, если категория задана. */
    public function test_is_applicable_returns_true_when_user_has_category(): void
    {
        $resolver = new DeliveryCostResolver;

        $this->assertTrue($resolver->isApplicable(1));
    }

    /** resolve возвращает ноль, если тарифы не настроены. */
    public function test_resolve_returns_zero_when_no_tiers_configured(): void
    {
        $resolver = new DeliveryCostResolver;

        $this->assertSame(0.0, $resolver->resolve(500.0, []));
    }

    /** resolve выбирает тариф по убыванию min_items_total. */
    public function test_resolve_picks_tier_by_descending_min_items_total(): void
    {
        $resolver = new DeliveryCostResolver;

        $tiers = [
            new DeliveryTierDto(minItemsTotal: 1000.0, deliveryCost: 0.0),
            new DeliveryTierDto(minItemsTotal: 0.0, deliveryCost: 200.0),
        ];

        $this->assertSame(200.0, $resolver->resolve(999.0, $tiers));
        $this->assertSame(0.0, $resolver->resolve(1000.0, $tiers));
        $this->assertSame(0.0, $resolver->resolve(1500.0, $tiers));
    }

    /** resolve возвращает ноль, если ни один порог тарифа не подходит. */
    public function test_resolve_returns_zero_when_no_tier_threshold_matches(): void
    {
        $resolver = new DeliveryCostResolver;

        $tiers = [
            new DeliveryTierDto(minItemsTotal: 1000.0, deliveryCost: 0.0),
        ];

        $this->assertSame(0.0, $resolver->resolve(500.0, $tiers));
    }

    /** resolveNextTier возвращает null на лучшем тарифе. */
    public function test_resolve_next_tier_returns_null_on_best_tier(): void
    {
        $resolver = new DeliveryCostResolver;

        $tiers = [
            new DeliveryTierDto(minItemsTotal: 1000.0, deliveryCost: 0.0),
            new DeliveryTierDto(minItemsTotal: 0.0, deliveryCost: 200.0),
        ];

        $this->assertNull($resolver->resolveNextTier(1000.0, $tiers));
        $this->assertNull($resolver->resolveNextTier(1500.0, $tiers));
    }

    /** resolveNextTier возвращает более высокий порог, когда сумма ниже текущего тарифа. */
    public function test_resolve_next_tier_returns_higher_threshold_when_below_current_tier(): void
    {
        $resolver = new DeliveryCostResolver;

        $tiers = [
            new DeliveryTierDto(minItemsTotal: 1000.0, deliveryCost: 0.0),
            new DeliveryTierDto(minItemsTotal: 0.0, deliveryCost: 200.0),
        ];

        $nextTier = $resolver->resolveNextTier(999.0, $tiers);

        $this->assertNotNull($nextTier);
        $this->assertSame(1000.0, $nextTier->minItemsTotal);
        $this->assertSame(0.0, $nextTier->deliveryCost);
    }

    /** resolveNextTier возвращает наивысший тариф, если порог не совпал. */
    public function test_resolve_next_tier_returns_highest_tier_when_no_threshold_matched(): void
    {
        $resolver = new DeliveryCostResolver;

        $tiers = [
            new DeliveryTierDto(minItemsTotal: 1000.0, deliveryCost: 0.0),
            new DeliveryTierDto(minItemsTotal: 0.0, deliveryCost: 200.0),
        ];

        $nextTier = $resolver->resolveNextTier(500.0, $tiers);

        $this->assertNotNull($nextTier);
        $this->assertSame(1000.0, $nextTier->minItemsTotal);
        $this->assertSame(0.0, $nextTier->deliveryCost);
    }

    /** resolveNextTier возвращает null, если тарифы не настроены. */
    public function test_resolve_next_tier_returns_null_when_no_tiers_configured(): void
    {
        $resolver = new DeliveryCostResolver;

        $this->assertNull($resolver->resolveNextTier(500.0, []));
    }
}
