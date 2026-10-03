import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';
import { looksLikeEnumMatchNamesSystem } from '../src/orchestrator/buildMatchPrompt.js';
import { classifyMatchResults } from '../src/orchestrator/classifyMatchResults.js';
import { SECTION_RESULT_CAP } from '../src/orchestrator/constants.js';
import { PriceDiffBuilder } from '../src/orchestrator/PriceDiffBuilder.js';
import { runMatch } from '../src/orchestrator/runMatch.js';
import { VpsOnlyCreateBuilder } from '../src/orchestrator/VpsOnlyCreateBuilder.js';
import type {
  BrisklySnapshotItem,
  ComboCatalogPromptDto,
  MatchLineResult,
  SourceMenuLine,
} from '../src/orchestrator/types.js';

const fixturesDir = join(dirname(fileURLToPath(import.meta.url)), '..', 'fixtures');

function loadMatchFixture() {
  return JSON.parse(readFileSync(join(fixturesDir, 'match-run.json'), 'utf8')) as {
    prompt: ComboCatalogPromptDto;
    source_lines: SourceMenuLine[];
    briskly_snapshot: BrisklySnapshotItem[];
    llm_response: string;
  };
}

describe('PriceDiffBuilder / VpsOnlyCreateBuilder', () => {
  it('PriceDiffBuilder пропускает равные цены', () => {
    const builder = new PriceDiffBuilder();
    builder.add({
      sourceLine: {
        line_key: 'a',
        type: 'single',
        display_name: 'A',
        price: '100.00',
      },
      brisklyItemId: 1,
      brisklyDisplayName: 'A',
      brisklyPrice: '100',
    });
    builder.add({
      sourceLine: {
        line_key: 'b',
        type: 'single',
        display_name: 'B',
        price: '110.00',
      },
      brisklyItemId: 2,
      brisklyDisplayName: 'B',
      brisklyPrice: 100,
    });

    expect(builder.build()).toEqual([
      {
        line_key: 'b',
        display_name: 'B',
        briskly_item_id: 2,
        briskly_display_name: 'B',
        source_price: '110.00',
        briskly_price: '100.00',
      },
    ]);
  });

  it('VpsOnlyCreateBuilder берёт display_name и price из source', () => {
    const item = new VpsOnlyCreateBuilder()
      .add({
        line_key: 'vps-only',
        type: 'single',
        display_name: 'Новое блюдо',
        price: '55',
      })
      .build()[0];

    expect(item).toEqual({
      line_key: 'vps-only',
      display_name: 'Новое блюдо',
      source_price: '55.00',
    });
  });
});

describe('classifyMatchResults 1D/2B', () => {
  it('fixture: price diff + create + drop briskly-only + equal + ambiguous + strip LLM price', async () => {
    const fixture = loadMatchFixture();
    expect(looksLikeEnumMatchNamesSystem(fixture.prompt.system)).toBe(true);

    const output = await runMatch({
      prompt: fixture.prompt,
      sourceLines: fixture.source_lines,
      brisklySnapshot: fixture.briskly_snapshot,
      matcher: async () => fixture.llm_response,
    });

    // LLM price отброшен
    expect(output.matchLines.find((l) => l.line_key === 'single:11')?.candidates[0]).toEqual({
      id: 101,
      name: 'Куриное филе с грибами (соус)',
    });

    const { syncResults } = output;

    // A: single:11 (250 vs 240), combo:11+12 (370 vs 400)
    expect(syncResults.price_updates.total).toBe(2);
    expect(syncResults.price_updates.truncated).toBe(false);
    expect(syncResults.price_updates.items.map((i) => i.line_key).sort()).toEqual([
      'combo:11+12',
      'single:11',
    ]);

    // B: VPS-only
    expect(syncResults.creates.total).toBe(1);
    expect(syncResults.creates.items[0]?.line_key).toBe('single:14');
    expect(syncResults.creates.items[0]?.display_name).toBe('Только в VPS блюдо');

    // equal: single:12 (120=120)
    expect(syncResults.counts.equal_price).toBe(1);

    // ambiguous: single:13 with 2 candidates
    expect(syncResults.counts.ambiguous).toBe(1);

    // briskly-only 999 + possibly unmatched 104 (ambiguous so not counted as matched)
    // 104 was in ambiguous candidates but we only mark matched when unambiguous single candidate.
    // So unmatched briskly: 104 and 999 (and maybe others not in matched set)
    // Matched: 101, 102, 103. Unmatched: 104, 999 → skipped_briskly_only = 2
    expect(syncResults.counts.skipped_briskly_only).toBe(2);

    // briskly-only не в results
    const allKeys = [
      ...syncResults.price_updates.items.map((i) => i.briskly_item_id),
      ...syncResults.creates.items.map((i) => i.line_key),
    ];
    expect(allKeys).not.toContain(999);
  });

  it('cap 25 + truncated', () => {
    const sourceLines: SourceMenuLine[] = [];
    const matchLines: MatchLineResult[] = [];
    const brisklySnapshot: BrisklySnapshotItem[] = [];

    for (let i = 1; i <= 30; i += 1) {
      sourceLines.push({
        line_key: `single:${i}`,
        type: 'single',
        display_name: `Dish ${i}`,
        price: '100.00',
      });
      brisklySnapshot.push({ id: i, name: `Dish ${i}`, price: '90.00' });
      matchLines.push({
        line_key: `single:${i}`,
        display_name: `Dish ${i}`,
        compare_name: `dish ${i}`,
        candidates: [{ id: i, name: `Dish ${i}` }],
      });
    }

    // +1 VPS-only creates beyond cap
    for (let i = 100; i < 130; i += 1) {
      sourceLines.push({
        line_key: `create:${i}`,
        type: 'single',
        display_name: `New ${i}`,
        price: '10.00',
      });
      matchLines.push({
        line_key: `create:${i}`,
        display_name: `New ${i}`,
        compare_name: `new ${i}`,
        candidates: [],
      });
    }

    // + briskly-only
    brisklySnapshot.push({ id: 9999, name: 'Only Briskly', price: '1' });

    const results = classifyMatchResults({
      sourceLines,
      brisklySnapshot,
      matchLines,
      cap: SECTION_RESULT_CAP,
    });

    expect(results.price_updates.total).toBe(30);
    expect(results.price_updates.shown).toBe(25);
    expect(results.price_updates.truncated).toBe(true);

    expect(results.creates.total).toBe(30);
    expect(results.creates.shown).toBe(25);
    expect(results.creates.truncated).toBe(true);

    expect(results.counts.skipped_briskly_only).toBe(1);
  });
});
