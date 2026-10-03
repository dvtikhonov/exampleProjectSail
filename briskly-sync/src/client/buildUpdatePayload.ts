import type { BrisklyItemDetails, BrisklyUpdateItemPayload } from '../types.js';

function asNumber(value: unknown, fallback: number): number {
  if (typeof value === 'number' && Number.isFinite(value)) {
    return value;
  }
  if (typeof value === 'string' && value.trim() !== '') {
    const n = Number(value);
    if (Number.isFinite(n)) {
      return n;
    }
  }
  return fallback;
}

function asString(value: unknown, fallback = ''): string {
  if (typeof value === 'string') {
    return value;
  }
  if (value === null || value === undefined) {
    return fallback;
  }
  return String(value);
}

/**
 * Собирает payload update: все поля из get-by-id, меняется только price.
 * Остальные поля не затираются дефолтами сверх известных ключей API.
 */
export function buildUpdatePricePayload(
  item: BrisklyItemDetails,
  newPrice: number | string,
): BrisklyUpdateItemPayload {
  if (item.id === undefined || item.id === null) {
    throw new Error('buildUpdatePricePayload: item.id is required');
  }
  if (item.catalog_id === undefined || item.catalog_id === null) {
    throw new Error('buildUpdatePricePayload: item.catalog_id is required');
  }
  if (item.category_id === undefined || item.category_id === null) {
    throw new Error('buildUpdatePricePayload: item.category_id is required');
  }
  if (item.name === undefined || item.name === null) {
    throw new Error('buildUpdatePricePayload: item.name is required');
  }

  const price =
    typeof newPrice === 'number' ? newPrice : Number(String(newPrice).replace(',', '.'));
  if (!Number.isFinite(price)) {
    throw new Error(`buildUpdatePricePayload: invalid price: ${String(newPrice)}`);
  }

  return {
    id: Number(item.id),
    name: asString(item.name),
    catalog_id: Number(item.catalog_id),
    category_id: Number(item.category_id),
    barcode: asString(item.barcode, ''),
    barcodes: Array.isArray(item.barcodes) ? item.barcodes : [],
    cost: item.cost ?? 0,
    price,
    modifications: Array.isArray(item.modifications) ? item.modifications : [],
    vat_mode: asNumber(item.vat_mode, 0),
    vat_rate: asNumber(item.vat_rate, 0),
    unit_id: asNumber(item.unit_id, 0),
    unit_dimension: asNumber(item.unit_dimension, 0),
    file: null,
    status: asNumber(item.status, 1),
    extra_code_type: asNumber(item.extra_code_type, 0),
    age_limit: asNumber(item.age_limit, 0),
    heating_enabled: asNumber(item.heating_enabled, 0),
    heating_duration: asNumber(item.heating_duration, 0),
    heating_power: asNumber(item.heating_power, 0),
    sticker_enabled: asNumber(item.sticker_enabled, 1),
    article: asString(item.article, ''),
    suggested_item_ids: Array.isArray(item.suggested_item_ids)
      ? item.suggested_item_ids.map(Number)
      : [],
    props: Array.isArray(item.props) ? item.props : [],
  };
}
