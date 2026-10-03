<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTO\Food\BrisklySync\BrisklySnapshotItemDto;
use App\DTO\Food\BrisklySync\SourceMenuLineDto;
use App\Enums\Food\Menu\DailyMenuLineType;
use App\Services\Food\BrisklySync\BrisklySyncClarificationExclusion;
use PHPUnit\Framework\TestCase;

class BrisklySyncClarificationExclusionTest extends TestCase
{
    public function test_needles_from_tolko_bez_shuby(): void
    {
        $needles = BrisklySyncClarificationExclusion::needles('только без шубы');

        $this->assertSame(['шуб'], $needles);
    }

    public function test_needles_empty_for_normalization_clarification(): void
    {
        $this->assertSame([], BrisklySyncClarificationExclusion::needles('игнорировать скобки и вес'));
        $this->assertSame([], BrisklySyncClarificationExclusion::needles(null));
        $this->assertSame([], BrisklySyncClarificationExclusion::needles('   '));
    }

    public function test_filters_source_and_briskly_with_shuba(): void
    {
        $needles = BrisklySyncClarificationExclusion::needles('только без шубы');

        $source = [
            new SourceMenuLineDto('single:1', DailyMenuLineType::Single, 'Сельдь под шубой', '65.00', [1]),
            new SourceMenuLineDto('single:2', DailyMenuLineType::Single, 'Сельдь слабосоленая с отварным картофелем', '120.00', [2]),
        ];
        $snapshot = [
            new BrisklySnapshotItemDto(10, 'Салат "Сельдь под шубой", 130г', '75.00'),
            new BrisklySnapshotItemDto(20, 'Сельдь слабосоленая с отварным картофелем, 110г', '120.00'),
        ];

        $filteredSource = BrisklySyncClarificationExclusion::filterSourceLines($source, $needles);
        $filteredSnapshot = BrisklySyncClarificationExclusion::filterSnapshot($snapshot, $needles);

        $this->assertCount(1, $filteredSource);
        $this->assertSame('single:2', $filteredSource[0]->lineKey);
        $this->assertCount(1, $filteredSnapshot);
        $this->assertSame(20, $filteredSnapshot[0]->id);
    }

    public function test_allows_when_no_needles(): void
    {
        $this->assertTrue(BrisklySyncClarificationExclusion::allows('Сельдь под шубой', []));
    }
}
