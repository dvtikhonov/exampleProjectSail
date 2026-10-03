<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTO\Food\BrisklySync\BrisklySnapshotItemDto;
use App\DTO\Food\BrisklySync\MatchCandidateDto;
use App\DTO\Food\BrisklySync\MatchLineResultDto;
use App\DTO\Food\BrisklySync\SourceMenuLineDto;
use App\Enums\Food\Menu\DailyMenuLineType;
use App\Infrastructure\Laravel\LaravelHttpClient;
use App\Services\Food\BrisklySync\BrisklySyncMatchClassifier;
use App\Services\Food\BrisklySync\BrisklySyncPrice;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class BrisklySyncMatchClassifierTest extends TestCase
{
    public function test_classifies_price_diff_create_equal_ambiguous_and_briskly_only(): void
    {
        $classifier = new BrisklySyncMatchClassifier();

        $source = [
            new SourceMenuLineDto('single:1', DailyMenuLineType::Single, 'A', '100.00', [1]),
            new SourceMenuLineDto('single:2', DailyMenuLineType::Single, 'B', '120.00', [2]),
            new SourceMenuLineDto('single:3', DailyMenuLineType::Single, 'C', '50.00', [3]),
            new SourceMenuLineDto('single:4', DailyMenuLineType::Single, 'D', '10.00', [4]),
        ];

        $snapshot = [
            new BrisklySnapshotItemDto(10, 'A', '90.00'),
            new BrisklySnapshotItemDto(20, 'B', '120.00'),
            new BrisklySnapshotItemDto(30, 'C1', '50.00'),
            new BrisklySnapshotItemDto(40, 'C2', '55.00'),
            new BrisklySnapshotItemDto(99, 'Only Briskly', '1.00'),
        ];

        $match = [
            new MatchLineResultDto('single:1', 'A', 'a', [new MatchCandidateDto(10, 'A')]),
            new MatchLineResultDto('single:2', 'B', 'b', [new MatchCandidateDto(20, 'B')]),
            new MatchLineResultDto('single:3', 'C', 'c', [
                new MatchCandidateDto(30, 'C1'),
                new MatchCandidateDto(40, 'C2'),
            ]),
            new MatchLineResultDto('single:4', 'D', 'd', []),
        ];

        $results = $classifier->classify($source, $snapshot, $match, 25);

        $this->assertSame(1, $results->priceUpdates->total);
        $this->assertSame('single:1', $results->priceUpdates->items[0]->lineKey);
        $this->assertSame('100.00', $results->priceUpdates->items[0]->sourcePrice);
        $this->assertSame('90.00', $results->priceUpdates->items[0]->brisklyPrice);

        $this->assertSame(1, $results->creates->total);
        $this->assertSame('single:4', $results->creates->items[0]->lineKey);
        $this->assertSame('D', $results->creates->items[0]->displayName);
        $this->assertSame('D', $results->creates->items[0]->createName());

        $this->assertSame(1, $results->counts->equalPrice);
        $this->assertSame(1, $results->counts->ambiguous);
        // 30/40 (ambiguous) + 99 (briskly-only) не в matched → 3
        $this->assertSame(3, $results->counts->skippedBrisklyOnly);
        $this->assertFalse($results->priceUpdates->truncated);
    }

    public function test_caps_sections_and_sets_truncated(): void
    {
        $classifier = new BrisklySyncMatchClassifier();
        $source = [];
        $match = [];
        for ($i = 1; $i <= 30; $i++) {
            $key = 'single:'.$i;
            $source[] = new SourceMenuLineDto($key, DailyMenuLineType::Single, 'Item '.$i, '10.00', [$i]);
            $match[] = new MatchLineResultDto($key, 'Item '.$i, 'item '.$i, []);
        }

        $results = $classifier->classify($source, [], $match, 25);

        $this->assertSame(30, $results->creates->total);
        $this->assertSame(25, $results->creates->shown);
        $this->assertTrue($results->creates->truncated);
    }

    public function test_price_hash_is_stable(): void
    {
        $lines = [
            new SourceMenuLineDto('b', DailyMenuLineType::Single, 'B', '2.00', [2]),
            new SourceMenuLineDto('a', DailyMenuLineType::Single, 'A', '1.5', [1]),
        ];

        $this->assertSame(
            BrisklySyncPrice::hashSourcePrices($lines),
            BrisklySyncPrice::hashSourcePrices(array_reverse($lines)),
        );
    }

    public function test_llm_price_in_match_payload_is_ignored_source_price_wins(): void
    {
        $classifier = new BrisklySyncMatchClassifier();

        // LLM прислал price в candidate — DTO его отбрасывает; цена только из source/snapshot.
        $candidate = MatchCandidateDto::fromArray([
            'id' => 10,
            'name' => 'A',
            'price' => '1.00',
        ]);
        $this->assertSame(['id' => 10, 'name' => 'A'], $candidate->toArray());

        $matchLine = MatchLineResultDto::fromArray([
            'line_key' => 'single:1',
            'display_name' => 'A',
            'compare_name' => 'a',
            'price' => '1.00',
            'candidates' => [
                ['id' => 10, 'name' => 'A', 'price' => '1.00'],
            ],
        ]);
        $this->assertArrayNotHasKey('price', $matchLine->toArray());

        $results = $classifier->classify(
            [new SourceMenuLineDto('single:1', DailyMenuLineType::Single, 'A', '100.00', [1])],
            [new BrisklySnapshotItemDto(10, 'A', '90.00')],
            [$matchLine],
            25,
        );

        $this->assertSame('100.00', $results->priceUpdates->items[0]->sourcePrice);
        $this->assertSame('90.00', $results->priceUpdates->items[0]->brisklyPrice);
    }

    public function test_relative_delta_threshold_for_large_confirm(): void
    {
        $this->assertSame(0.0, BrisklySyncPrice::relativeDelta('100', '100'));
        $this->assertGreaterThan(0.5, BrisklySyncPrice::relativeDelta('200', '100'));
        $this->assertLessThanOrEqual(0.5, BrisklySyncPrice::relativeDelta('140', '100'));
    }

    public function test_create_proposal_keeps_briskly_create_name_with_weight(): void
    {
        $classifier = new BrisklySyncMatchClassifier();

        $results = $classifier->classify(
            [
                new SourceMenuLineDto(
                    'single:1',
                    DailyMenuLineType::Single,
                    'Тостер Тест',
                    '55.00',
                    [1],
                    'Тостер Тест, 200г',
                ),
            ],
            [],
            [new MatchLineResultDto('single:1', 'Тостер Тест', 'тостер тест', [])],
            25,
        );

        $this->assertSame(1, $results->creates->total);
        $this->assertSame('Тостер Тест', $results->creates->items[0]->displayName);
        $this->assertSame('Тостер Тест, 200г', $results->creates->items[0]->createName());
        $this->assertSame(
            'Тостер Тест, 200г',
            $results->creates->items[0]->toArray()['briskly_create_name'],
        );
    }

    public function test_http_client_does_not_disable_ssl_verify(): void
    {
        $source = file_get_contents(
            (new ReflectionClass(LaravelHttpClient::class))->getFileName() ?: '',
        );
        $this->assertIsString($source);
        $this->assertStringNotContainsString('withoutVerifying', $source);
        $this->assertStringNotContainsString('CURLOPT_SSL_VERIFYPEER', $source);
    }
}
