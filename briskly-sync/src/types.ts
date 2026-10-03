/**
 * Типы ответов/запросов Briskly Business company API
 * (кабинет briskly.business, не публичный OpenAPI).
 */

/** Краткая позиция из get-list. */
export interface BrisklyListItem {
  id: number;
  name: string;
  price: number | string;
  category_id?: number;
  catalog_id?: number;
  status?: number;
  quantity_calculated?: number | string;
  category?: { name?: string } | string | null;
}

export interface BrisklyListMeta {
  total_pages?: number;
  total?: number;
  /** Как в реальном ответе Briskly get-list. */
  total_items?: number;
  page?: number;
  limit?: number;
}

export interface BrisklyListResponse {
  items: BrisklyListItem[];
  meta?: BrisklyListMeta;
}

/** Детали товара из get-by-id (поля используются при merge update). */
export interface BrisklyItemDetails {
  id: number;
  name: string;
  catalog_id: number;
  category_id: number;
  price: number | string;
  barcode?: string;
  barcodes?: unknown[];
  cost?: number | string;
  modifications?: unknown[];
  vat_mode?: number;
  vat_rate?: number;
  unit_id?: number;
  unit_dimension?: number;
  file?: unknown;
  status?: number;
  extra_code_type?: number;
  age_limit?: number;
  heating_enabled?: number;
  heating_duration?: number;
  heating_power?: number;
  sticker_enabled?: number;
  article?: string;
  suggested_item_ids?: number[];
  props?: unknown[];
  [key: string]: unknown;
}

/** Payload для POST .../item/update (v2). */
export interface BrisklyUpdateItemPayload {
  id: number;
  name: string;
  catalog_id: number;
  category_id: number;
  barcode: string;
  barcodes: unknown[];
  cost: number | string;
  price: number | string;
  modifications: unknown[];
  vat_mode: number;
  vat_rate: number;
  unit_id: number;
  unit_dimension: number;
  file: null;
  status: number;
  extra_code_type: number;
  age_limit: number;
  heating_enabled: number;
  heating_duration: number;
  heating_power: number;
  sticker_enabled: number;
  article: string;
  suggested_item_ids: number[];
  props: unknown[];
}

/**
 * Payload для POST .../item/create (v1).
 * По разведке Network (`main-*.js` кабинета): create = company v1,
 * набор полей совместим с update без `id`.
 */
export interface BrisklyCreateItemPayload {
  name: string;
  catalog_id: number;
  category_id: number;
  parent_id: number;
  barcode: string;
  barcodes: unknown[];
  cost: number | string;
  price: number | string;
  modifications: unknown[];
  vat_mode: number;
  vat_rate: number;
  unit_id: number;
  unit_dimension: number;
  file: null;
  status: number;
  extra_code_type: number;
  age_limit: number;
  heating_enabled: number;
  heating_duration: number;
  heating_power: number;
  sticker_enabled: number;
  article: string;
  text: string;
  suggested_item_ids: number[];
  props: unknown[];
}

export interface BrisklyCategory {
  id: number;
  name: string;
  catalog_id?: number;
  parent_id?: number;
  status?: number;
  [key: string]: unknown;
}

export interface BrisklyCategoryListResponse {
  items: BrisklyCategory[];
  meta?: BrisklyListMeta;
}

export interface ListItemsOptions {
  page?: number;
  limit?: number;
  /**
   * Подстрока имени (case-insensitive).
   * На одной странице (`listItems`) — фильтр только этой страницы.
   * Для CLI/поиска по каталогу используйте `searchItems` (обход страниц).
   * API category filter не используется.
   */
  searchText?: string | null;
  /**
   * Не слать filters[category_id] (всегда true по контракту синка).
   * Оставлено явно, чтобы случайно не вернуть category filter.
   */
  excludeCategoryFilter?: true;
}

/** Результат поиска с обходом страниц (API name-search нет — фильтр после fetch). */
export interface SearchItemsResult {
  items: BrisklyListItem[];
  /** Сколько совпадений нашли (до обрезки по maxResults). */
  matched: number;
  /** Сколько страниц API просмотрели. */
  pagesScanned: number;
  /** Всего страниц в каталоге (из meta). */
  totalPages: number;
  /** Всего позиций в каталоге (из meta), если API отдал. */
  totalItems: number | null;
  truncated: boolean;
}

export interface SearchItemsOptions {
  searchText: string;
  /** Максимум совпадений в ответе. */
  maxResults?: number;
  /** Размер страницы API. */
  pageSize?: number;
  delayMs?: number;
  maxPages?: number;
}

export interface SnapshotOptions {
  searchText?: string | null;
  limit?: number;
  /** Задержка между страницами, мс. */
  delayMs?: number;
  /** Жёсткий потолок страниц (защита от бесконечного каталога). */
  maxPages?: number;
}

export interface CreateItemInput {
  name: string;
  price: number | string;
  categoryId: number;
  catalogId: number;
  barcode?: string;
  article?: string;
  cost?: number | string;
  status?: number;
  vatMode?: number;
  vatRate?: number;
  unitId?: number;
  unitDimension?: number;
}

export interface UpdatePriceDryRunResult {
  itemId: number;
  oldPrice: number | string;
  newPrice: number | string;
  payload: BrisklyUpdateItemPayload;
}

export class BrisklyApiError extends Error {
  readonly statusCode: number;
  readonly path: string;

  constructor(message: string, statusCode: number, path: string) {
    super(message);
    this.name = 'BrisklyApiError';
    this.statusCode = statusCode;
    this.path = path;
  }
}
