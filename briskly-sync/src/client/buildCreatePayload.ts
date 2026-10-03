import type { BrisklyCreateItemPayload, CreateItemInput } from '../types.js';

/**
 * Payload CREATE по контракту кабинета (разведка Network, сент. 2026):
 * POST `/api/company/v1/dashboard/item/create` (`company.post`, не v2).
 * Поля зеркалят update без `id`; обязательные для синка: name, price, category_id, catalog_id.
 *
 * Источник: `briskly.business/main-*.js` → `createItem` → path `dashboard/item/create`.
 * См. `docs/briskly-api-recon.md`.
 */
export function buildCreateItemPayload(input: CreateItemInput): BrisklyCreateItemPayload {
  const name = input.name?.trim();
  if (!name) {
    throw new Error('buildCreateItemPayload: name is required');
  }
  if (
    input.categoryId === undefined ||
    input.categoryId === null ||
    !Number.isFinite(Number(input.categoryId))
  ) {
    throw new Error('buildCreateItemPayload: categoryId is required');
  }
  if (
    input.catalogId === undefined ||
    input.catalogId === null ||
    !Number.isFinite(Number(input.catalogId))
  ) {
    throw new Error('buildCreateItemPayload: catalogId is required');
  }

  const price =
    typeof input.price === 'number'
      ? input.price
      : Number(String(input.price).replace(',', '.'));
  if (!Number.isFinite(price)) {
    throw new Error(`buildCreateItemPayload: invalid price: ${String(input.price)}`);
  }

  return {
    name,
    catalog_id: Number(input.catalogId),
    category_id: Number(input.categoryId),
    parent_id: 0,
    // Как в кабинете: `generate` просит Briskly выдать штрихкод в ответе create.
    barcode: input.barcode ?? 'generate',
    barcodes: [],
    cost: input.cost ?? 0,
    price,
    modifications: [],
    vat_mode: input.vatMode ?? 0,
    vat_rate: input.vatRate ?? 0,
    // ОКЕИ «Штука» (unit_796); 0 на create даёт HTTP 400 в company API.
    unit_id: input.unitId ?? 796,
    unit_dimension: input.unitDimension ?? 0,
    file: null,
    status: input.status ?? 1,
    extra_code_type: 0,
    age_limit: 0,
    heating_enabled: 0,
    heating_duration: 0,
    heating_power: 0,
    sticker_enabled: 1,
    article: input.article ?? '',
    text: '',
    suggested_item_ids: [],
    props: [],
  };
}
