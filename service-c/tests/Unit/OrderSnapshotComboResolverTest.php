<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\OrderSnapshotComboResolver;
use Tests\TestCase;

class OrderSnapshotComboResolverTest extends TestCase
{
    private OrderSnapshotComboResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new OrderSnapshotComboResolver;
    }

    public function test_format_combo_label_includes_partner_name(): void
    {
        $itemsSnapshot = [
            [
                'dish_id' => 1,
                'dish_name' => 'Бургер',
                'combo_ref' => 'combo-1',
                'combo_partner_dish_ids' => [2],
            ],
            [
                'dish_id' => 2,
                'dish_name' => 'Картофель фри',
                'combo_ref' => 'combo-1',
                'combo_partner_dish_ids' => [1],
            ],
        ];

        $label = $this->resolver->formatComboLabel($itemsSnapshot[0], $itemsSnapshot);

        $this->assertSame('Входит в комбо: Картофель фри', $label);
    }

    public function test_format_combo_label_without_partner_name_uses_short_form(): void
    {
        $itemsSnapshot = [
            [
                'dish_id' => 1,
                'dish_name' => 'Бургер',
                'combo_ref' => 'combo-1',
                'combo_partner_dish_ids' => [99],
            ],
        ];

        $label = $this->resolver->formatComboLabel($itemsSnapshot[0], $itemsSnapshot);

        $this->assertSame('Входит в комбо', $label);
    }

    public function test_format_combo_label_returns_null_for_regular_item(): void
    {
        $itemsSnapshot = [
            [
                'dish_id' => 1,
                'dish_name' => 'Салат',
            ],
        ];

        $label = $this->resolver->formatComboLabel($itemsSnapshot[0], $itemsSnapshot);

        $this->assertNull($label);
    }
}
