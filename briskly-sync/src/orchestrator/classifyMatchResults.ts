import { SECTION_RESULT_CAP } from './constants.js';
import { PriceDiffBuilder } from './PriceDiffBuilder.js';
import { pricesEqual, normalizePrice } from './price.js';
import type {
  BrisklySnapshotItem,
  ClassifyMatchInput,
  MatchLineResult,
  SyncResults,
  SyncResultsSection,
} from './types.js';
import { VpsOnlyCreateBuilder } from './VpsOnlyCreateBuilder.js';

/**
 * Серверная классификация после Cursor match (1D / 2B):
 * - matched + price diff → PriceDiff (cap 25)
 * - matched + equal → счётчик equal_price
 * - source без briskly → VpsOnlyCreate (cap 25)
 * - briskly без source → drop + skipped_briskly_only
 * - ambiguous (>1 candidate) → счётчик, не в таблицах
 */
export function classifyMatchResults(input: ClassifyMatchInput): SyncResults {
  const cap = input.cap ?? SECTION_RESULT_CAP;
  const matchByKey = indexMatchLines(input.matchLines);
  const brisklyById = new Map<number, BrisklySnapshotItem>();
  for (const item of input.brisklySnapshot) {
    brisklyById.set(Number(item.id), item);
  }

  const priceDiff = new PriceDiffBuilder();
  const creates = new VpsOnlyCreateBuilder();
  const matchedBrisklyIds = new Set<number>();

  let ambiguous = 0;
  let equalPrice = 0;

  for (const sourceLine of input.sourceLines) {
    const match = matchByKey.get(sourceLine.line_key);

    if (!match || match.candidates.length === 0) {
      creates.add(sourceLine);
      continue;
    }

    if (match.candidates.length > 1) {
      ambiguous += 1;
      continue;
    }

    const candidate = match.candidates[0]!;
    const briskly = brisklyById.get(Number(candidate.id));
    if (!briskly) {
      // Кандидат вне snapshot — как отсутствие пары → CREATE.
      creates.add(sourceLine);
      continue;
    }

    matchedBrisklyIds.add(Number(briskly.id));

    if (pricesEqual(sourceLine.price, briskly.price)) {
      equalPrice += 1;
      continue;
    }

    priceDiff.add({
      sourceLine,
      brisklyItemId: Number(briskly.id),
      brisklyDisplayName: String(briskly.name ?? candidate.name),
      brisklyPrice: briskly.price,
      compareName: match.compare_name,
    });
  }

  let skippedBrisklyOnly = 0;
  for (const item of input.brisklySnapshot) {
    if (!matchedBrisklyIds.has(Number(item.id))) {
      skippedBrisklyOnly += 1;
    }
  }

  return {
    price_updates: capSection(priceDiff.build(), cap),
    creates: capSection(creates.build(), cap),
    counts: {
      skipped_briskly_only: skippedBrisklyOnly,
      ambiguous,
      equal_price: equalPrice,
    },
  };
}

function indexMatchLines(lines: MatchLineResult[]): Map<string, MatchLineResult> {
  const map = new Map<string, MatchLineResult>();
  for (const line of lines) {
    if (!map.has(line.line_key)) {
      map.set(line.line_key, line);
    }
  }
  return map;
}

function capSection<T>(all: T[], cap: number): SyncResultsSection<T> {
  const total = all.length;
  const items = all.slice(0, cap);
  return {
    items,
    total,
    shown: items.length,
    truncated: total > items.length,
  };
}

/** Для тестов/отладки: нормализовать цену proposal. */
export { normalizePrice };
