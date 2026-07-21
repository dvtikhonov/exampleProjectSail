<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\OrderSnapshotComboResolver;
use Tests\TestCase;

class OrderSnapshotComboResolverTest extends TestCase
{
    private OrderSnapshotComboResolver $resolver;

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new OrderSnapshotComboResolver;
    }

    /** formatComboLabel включает имя партнёра. */
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

    /** formatComboLabel без имени партнёра использует короткую форму. */
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

    /** formatComboLabel возвращает null для обычной позиции. */
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

    /** groupSnapshotItems объединяет пары комбо и оставляет обычные позиции. */
    public function test_group_snapshot_items_groups_combo_pairs(): void
    {
        $itemsSnapshot = [
            [
                'dish_id' => 1,
                'dish_name' => 'Салат',
                'quantity' => 2,
            ],
            [
                'dish_id' => 2,
                'dish_name' => 'Бургер',
                'quantity' => 1,
                'combo_ref' => 'combo-1',
                'combo_partner_dish_ids' => [3],
            ],
            [
                'dish_id' => 3,
                'dish_name' => 'Картофель фри',
                'quantity' => 1,
                'combo_ref' => 'combo-1',
                'combo_partner_dish_ids' => [2],
            ],
        ];

        $groups = $this->resolver->groupSnapshotItems($itemsSnapshot);

        $this->assertCount(2, $groups);
        $this->assertSame('item', $groups[0]['type']);
        $this->assertSame(2, $groups[0]['quantity']);
        $this->assertSame('combo', $groups[1]['type']);
        $this->assertCount(2, $groups[1]['items']);
        $this->assertSame(1, $groups[1]['quantity']);
    }
}
