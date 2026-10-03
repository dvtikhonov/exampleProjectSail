import type { PriceDiffItem, SourceMenuLine } from './types.js';
import { normalizePrice } from './price.js';

export interface PriceDiffPairInput {
  sourceLine: SourceMenuLine;
  brisklyItemId: number;
  brisklyDisplayName: string;
  brisklyPrice: number | string;
  compareName?: string;
}

/**
 * Builder секции A: matched + price diff (равные цены не попадают сюда).
 */
export class PriceDiffBuilder {
  private readonly items: PriceDiffItem[] = [];

  add(pair: PriceDiffPairInput): this {
    const sourcePrice = normalizePrice(pair.sourceLine.price);
    const brisklyPrice = normalizePrice(pair.brisklyPrice);
    if (sourcePrice === brisklyPrice) {
      return this;
    }

    this.items.push({
      line_key: pair.sourceLine.line_key,
      display_name: pair.sourceLine.display_name,
      briskly_item_id: pair.brisklyItemId,
      briskly_display_name: pair.brisklyDisplayName,
      source_price: sourcePrice,
      briskly_price: brisklyPrice,
    });
    return this;
  }

  build(): PriceDiffItem[] {
    return [...this.items];
  }
}

export function buildPriceDiffItem(pair: PriceDiffPairInput): PriceDiffItem | null {
  const builder = new PriceDiffBuilder();
  builder.add(pair);
  return builder.build()[0] ?? null;
}
