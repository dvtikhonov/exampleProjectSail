import { describe, expect, it } from 'vitest';
import { buildCreateItemPayload } from '../src/client/buildCreatePayload.js';

describe('buildCreateItemPayload', () => {
  it('собирает create без id с name/price/category/catalog', () => {
    const payload = buildCreateItemPayload({
      name: 'Рис с овощами',
      price: '120.00',
      categoryId: 55,
      catalogId: 7,
    });

    expect(payload).toMatchObject({
      name: 'Рис с овощами',
      price: 120,
      category_id: 55,
      catalog_id: 7,
      parent_id: 0,
      barcode: 'generate',
      barcodes: [],
      cost: 0,
      unit_id: 796,
      status: 1,
      sticker_enabled: 1,
      article: '',
      text: '',
      file: null,
    });
    expect(payload).not.toHaveProperty('id');
  });

  it('требует name и валидные category/catalog', () => {
    expect(() =>
      buildCreateItemPayload({
        name: '  ',
        price: 1,
        categoryId: 1,
        catalogId: 1,
      }),
    ).toThrow(/name/);

    expect(() =>
      buildCreateItemPayload({
        name: 'X',
        price: 1,
        categoryId: Number.NaN,
        catalogId: 1,
      }),
    ).toThrow(/categoryId/);
  });
});
