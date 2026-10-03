import { describe, expect, it } from 'vitest';
import { buildUpdatePricePayload } from '../src/client/buildUpdatePayload.js';
import type { BrisklyItemDetails } from '../src/types.js';

function sampleItem(overrides: Partial<BrisklyItemDetails> = {}): BrisklyItemDetails {
  return {
    id: 101,
    name: 'Суп дня',
    catalog_id: 10,
    category_id: 20,
    price: 150,
    barcode: 'OLD',
    barcodes: ['a'],
    cost: 40,
    modifications: [{ id: 1 }],
    vat_mode: 1,
    vat_rate: 20,
    unit_id: 5,
    unit_dimension: 1,
    status: 1,
    extra_code_type: 0,
    age_limit: 0,
    heating_enabled: 1,
    heating_duration: 30,
    heating_power: 800,
    sticker_enabled: 1,
    article: 'A-1',
    suggested_item_ids: [9],
    props: [{ k: 'v' }],
    ...overrides,
  };
}

describe('buildUpdatePricePayload', () => {
  it('меняет только price и сохраняет остальные поля из get-by-id', () => {
    const item = sampleItem();
    const payload = buildUpdatePricePayload(item, '199.50');

    expect(payload.price).toBe(199.5);
    expect(payload.id).toBe(101);
    expect(payload.name).toBe('Суп дня');
    expect(payload.catalog_id).toBe(10);
    expect(payload.category_id).toBe(20);
    expect(payload.barcode).toBe('OLD');
    expect(payload.barcodes).toEqual(['a']);
    expect(payload.cost).toBe(40);
    expect(payload.modifications).toEqual([{ id: 1 }]);
    expect(payload.vat_mode).toBe(1);
    expect(payload.vat_rate).toBe(20);
    expect(payload.unit_id).toBe(5);
    expect(payload.unit_dimension).toBe(1);
    expect(payload.file).toBeNull();
    expect(payload.heating_enabled).toBe(1);
    expect(payload.heating_duration).toBe(30);
    expect(payload.heating_power).toBe(800);
    expect(payload.sticker_enabled).toBe(1);
    expect(payload.article).toBe('A-1');
    expect(payload.suggested_item_ids).toEqual([9]);
    expect(payload.props).toEqual([{ k: 'v' }]);
  });

  it('подставляет безопасные дефолты для пустых optional-полей', () => {
    const payload = buildUpdatePricePayload(
      sampleItem({
        barcode: undefined,
        barcodes: undefined,
        modifications: undefined,
        suggested_item_ids: undefined,
        props: undefined,
        sticker_enabled: undefined,
      }),
      10,
    );

    expect(payload.barcode).toBe('');
    expect(payload.barcodes).toEqual([]);
    expect(payload.modifications).toEqual([]);
    expect(payload.suggested_item_ids).toEqual([]);
    expect(payload.props).toEqual([]);
    expect(payload.sticker_enabled).toBe(1);
    expect(payload.price).toBe(10);
  });

  it('бросает на невалидной цене', () => {
    expect(() => buildUpdatePricePayload(sampleItem(), 'abc')).toThrow(/invalid price/);
  });
});
