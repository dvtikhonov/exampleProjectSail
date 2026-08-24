<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTO\Food\PhotoText\PhotoTextAgentItemDto;
use App\Enums\Food\PhotoText\PhotoTextComboRefGroupKind;
use App\Services\Food\PhotoText\PhotoTextComboRefGrouper;
use PHPUnit\Framework\TestCase;

class PhotoTextComboRefGrouperTest extends TestCase
{
    private PhotoTextComboRefGrouper $grouper;

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->grouper = new PhotoTextComboRefGrouper;
    }

    /** Позиции без combo_ref остаются одиночными в исходном порядке. */
    public function test_items_without_combo_ref_are_single_groups(): void
    {
        $items = [
            new PhotoTextAgentItemDto('Салат "Фасолька"', 2),
            new PhotoTextAgentItemDto('Гречка', 1),
        ];

        $groups = $this->grouper->group($items);

        $this->assertCount(2, $groups);
        $this->assertSame(PhotoTextComboRefGroupKind::Single, $groups[0]->kind);
        $this->assertSame('Салат "Фасолька"', $groups[0]->items[0]->name);
        $this->assertSame(PhotoTextComboRefGroupKind::Single, $groups[1]->kind);
        $this->assertSame('Гречка', $groups[1]->items[0]->name);
    }

    /** Один combo_ref и ровно две позиции с одинаковым qty — пара. */
    public function test_two_items_with_same_combo_ref_and_quantity_form_a_pair(): void
    {
        $comboRef = '11111111-1111-1111-1111-111111111111';
        $items = [
            new PhotoTextAgentItemDto('Филе минтая с овощами', 1, $comboRef),
            new PhotoTextAgentItemDto('Гречка', 1, $comboRef),
        ];

        $groups = $this->grouper->group($items);

        $this->assertCount(1, $groups);
        $this->assertSame(PhotoTextComboRefGroupKind::Pair, $groups[0]->kind);
        $this->assertCount(2, $groups[0]->items);
        $this->assertSame('Филе минтая с овощами', $groups[0]->items[0]->name);
        $this->assertSame('Гречка', $groups[0]->items[1]->name);
    }

    /** Неполная пара combo_ref — Unresolved. */
    public function test_single_item_with_combo_ref_is_unresolved(): void
    {
        $items = [
            new PhotoTextAgentItemDto('Филе минтая с овощами', 1, '11111111-1111-1111-1111-111111111111'),
        ];

        $groups = $this->grouper->group($items);

        $this->assertCount(1, $groups);
        $this->assertSame(PhotoTextComboRefGroupKind::Unresolved, $groups[0]->kind);
        $this->assertCount(1, $groups[0]->items);
    }

    /** Три позиции с одним combo_ref — Unresolved. */
    public function test_three_items_with_same_combo_ref_are_unresolved(): void
    {
        $comboRef = '22222222-2222-2222-2222-222222222222';
        $items = [
            new PhotoTextAgentItemDto('A', 1, $comboRef),
            new PhotoTextAgentItemDto('B', 1, $comboRef),
            new PhotoTextAgentItemDto('C', 1, $comboRef),
        ];

        $groups = $this->grouper->group($items);

        $this->assertCount(1, $groups);
        $this->assertSame(PhotoTextComboRefGroupKind::Unresolved, $groups[0]->kind);
        $this->assertCount(3, $groups[0]->items);
    }

    /** Две позиции с одним combo_ref и разным qty — Unresolved. */
    public function test_pair_with_different_quantities_is_unresolved(): void
    {
        $comboRef = '33333333-3333-3333-3333-333333333333';
        $items = [
            new PhotoTextAgentItemDto('Филе минтая с овощами', 1, $comboRef),
            new PhotoTextAgentItemDto('Гречка', 2, $comboRef),
        ];

        $groups = $this->grouper->group($items);

        $this->assertCount(1, $groups);
        $this->assertSame(PhotoTextComboRefGroupKind::Unresolved, $groups[0]->kind);
    }

    /** Одиночные и пары сохраняют относительный порядок первого появления. */
    public function test_preserves_order_of_singles_and_pairs(): void
    {
        $comboRef = '44444444-4444-4444-4444-444444444444';
        $items = [
            new PhotoTextAgentItemDto('Салат', 1),
            new PhotoTextAgentItemDto('Филе', 2, $comboRef),
            new PhotoTextAgentItemDto('Чай', 1),
            new PhotoTextAgentItemDto('Гречка', 2, $comboRef),
        ];

        $groups = $this->grouper->group($items);

        $this->assertCount(3, $groups);
        $this->assertSame(PhotoTextComboRefGroupKind::Single, $groups[0]->kind);
        $this->assertSame('Салат', $groups[0]->items[0]->name);
        $this->assertSame(PhotoTextComboRefGroupKind::Pair, $groups[1]->kind);
        $this->assertSame('Филе', $groups[1]->items[0]->name);
        $this->assertSame('Гречка', $groups[1]->items[1]->name);
        $this->assertSame(PhotoTextComboRefGroupKind::Single, $groups[2]->kind);
        $this->assertSame('Чай', $groups[2]->items[0]->name);
    }
}
