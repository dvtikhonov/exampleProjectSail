import type { SourceMenuLine, VpsOnlyCreateItem } from './types.js';
import { normalizePrice } from './price.js';

/**
 * Builder секции B: VPS есть, однозначной пары в Briskly нет (1D CREATE).
 * CREATE использует display_name + price из source (не compare_name).
 */
export class VpsOnlyCreateBuilder {
  private readonly items: VpsOnlyCreateItem[] = [];

  add(sourceLine: SourceMenuLine): this {
    this.items.push({
      line_key: sourceLine.line_key,
      display_name: sourceLine.display_name,
      source_price: normalizePrice(sourceLine.price),
    });
    return this;
  }

  build(): VpsOnlyCreateItem[] {
    return [...this.items];
  }
}

export function buildVpsOnlyCreateItem(sourceLine: SourceMenuLine): VpsOnlyCreateItem {
  return new VpsOnlyCreateBuilder().add(sourceLine).build()[0]!;
}
