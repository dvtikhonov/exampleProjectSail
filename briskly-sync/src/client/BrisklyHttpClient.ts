import { resolveClientConfig, type BrisklyClientConfig } from '../config.js';
import {
  BrisklyApiError,
  type BrisklyCategory,
  type BrisklyCategoryListResponse,
  type BrisklyCreateItemPayload,
  type BrisklyItemDetails,
  type BrisklyListItem,
  type BrisklyListResponse,
  type BrisklyUpdateItemPayload,
  type CreateItemInput,
  type ListItemsOptions,
  type SearchItemsOptions,
  type SearchItemsResult,
  type SnapshotOptions,
  type UpdatePriceDryRunResult,
} from '../types.js';
import { buildCreateItemPayload } from './buildCreatePayload.js';
import { buildUpdatePricePayload } from './buildUpdatePayload.js';
import {
  buildItemGetListParams,
  buildQueryString,
  matchesSearchText,
} from './query.js';

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

/**
 * HTTP-клиент Briskly company API.
 *
 * Endpoints (разведка кабинета + changePrices.php):
 * - GET  /v2/dashboard/item/get-list
 * - GET  /v1/dashboard/item/get-by-id
 * - POST /v2/dashboard/item/update
 * - GET  /v2/dashboard/category/get-list
 * - POST /v1/dashboard/item/create
 *
 * SSL verify включён (в отличие от старого PHP-скрипта).
 * Токен не логируется.
 */
export class BrisklyHttpClient {
  private readonly token: string;
  private readonly baseUrl: string;
  private readonly delayBetweenRequestsMs: number;
  private readonly defaultPageLimit: number;
  private readonly maxSnapshotPages: number;
  private readonly fetchImpl: typeof fetch;

  constructor(config: BrisklyClientConfig) {
    const resolved = resolveClientConfig(config);
    this.token = resolved.token;
    this.baseUrl = resolved.baseUrl;
    this.delayBetweenRequestsMs = resolved.delayBetweenRequestsMs;
    this.defaultPageLimit = resolved.defaultPageLimit;
    this.maxSnapshotPages = resolved.maxSnapshotPages;
    this.fetchImpl = resolved.fetchImpl;
  }

  /**
   * Сырая страница get-list **без** client-side search и **без** category filter.
   */
  async listItemsPage(options: {
    page?: number;
    limit?: number;
  } = {}): Promise<BrisklyListResponse> {
    const page = options.page ?? 1;
    const limit = options.limit ?? this.defaultPageLimit;
    const params = buildItemGetListParams({ page, limit });
    const qs = buildQueryString(params);
    const raw = await this.requestJson<BrisklyListResponse>(
      'GET',
      `/v2/dashboard/item/get-list?${qs}`,
    );

    return {
      items: Array.isArray(raw.items) ? raw.items : [],
      meta: raw.meta,
    };
  }

  /**
   * Одна страница списка. **Без** filters[category_id].
   * searchText фильтрует только эту страницу — для поиска по каталогу см. `searchItems`.
   */
  async listItems(options: ListItemsOptions = {}): Promise<BrisklyListResponse> {
    const raw = await this.listItemsPage({
      page: options.page,
      limit: options.limit,
    });
    const filtered = raw.items.filter((item) =>
      matchesSearchText(String(item.name ?? ''), options.searchText),
    );

    return {
      items: filtered,
      meta: raw.meta,
    };
  }

  /**
   * Поиск по имени: листает страницы get-list, фильтрует client-side
   * (у API нет надёжного name search в кабинете). Без category filter.
   * Останавливается, когда набрано `maxResults` совпадений или страницы кончились.
   */
  async searchItems(options: SearchItemsOptions): Promise<SearchItemsResult> {
    const needle = options.searchText?.trim();
    if (!needle) {
      throw new Error('searchItems: searchText is required');
    }

    const maxResults = options.maxResults ?? this.defaultPageLimit;
    const pageSize = options.pageSize ?? this.defaultPageLimit;
    const delayMs = options.delayMs ?? this.delayBetweenRequestsMs;
    const maxPages = options.maxPages ?? this.maxSnapshotPages;

    const matched: BrisklyListItem[] = [];
    let page = 1;
    let totalPages = 1;
    let totalItems: number | null = null;
    let pagesScanned = 0;

    do {
      const response = await this.listItemsPage({ page, limit: pageSize });
      pagesScanned += 1;
      totalPages = response.meta?.total_pages ?? page;
      if (typeof response.meta?.total_items === 'number') {
        totalItems = response.meta.total_items;
      } else if (typeof response.meta?.total === 'number') {
        totalItems = response.meta.total;
      }

      for (const item of response.items) {
        if (matchesSearchText(String(item.name ?? ''), needle)) {
          matched.push(item);
        }
      }

      const hasMorePages = page < totalPages && page < maxPages;
      const needMore = matched.length < maxResults;
      page += 1;
      if (needMore && hasMorePages) {
        await sleep(delayMs);
      } else {
        break;
      }
    } while (true);

    const items = matched.slice(0, maxResults);
    return {
      items,
      matched: matched.length,
      pagesScanned,
      totalPages,
      totalItems,
      // Неполный результат: срезали по maxResults или не обошли весь каталог.
      truncated: items.length < matched.length || pagesScanned < totalPages,
    };
  }

  /**
   * Snapshot каталога для матчинга: все страницы, без category filter,
   * опциональный search_text после fetch.
   */
  async listItemsSnapshot(options: SnapshotOptions = {}): Promise<BrisklyListItem[]> {
    const limit = options.limit ?? this.defaultPageLimit;
    const delayMs = options.delayMs ?? this.delayBetweenRequestsMs;
    const maxPages = options.maxPages ?? this.maxSnapshotPages;
    const all: BrisklyListItem[] = [];

    let page = 1;
    let totalPages = 1;

    do {
      const response = await this.listItemsPage({ page, limit });
      const pageItems = response.items.filter((item) =>
        matchesSearchText(String(item.name ?? ''), options.searchText),
      );
      all.push(...pageItems);
      totalPages = response.meta?.total_pages ?? page;
      page += 1;
      if (page <= totalPages && page <= maxPages) {
        await sleep(delayMs);
      }
    } while (page <= totalPages && page <= maxPages);

    return all;
  }

  async getItem(id: number): Promise<BrisklyItemDetails> {
    const qs = buildQueryString({ id });
    return this.requestJson<BrisklyItemDetails>(
      'GET',
      `/v1/dashboard/item/get-by-id?${qs}`,
    );
  }

  /**
   * Dry-run: get-by-id + merge price → payload без POST.
   */
  async prepareUpdateItemPrice(
    id: number,
    price: number | string,
  ): Promise<UpdatePriceDryRunResult> {
    const item = await this.getItem(id);
    const payload = buildUpdatePricePayload(item, price);
    return {
      itemId: id,
      oldPrice: item.price,
      newPrice: payload.price,
      payload,
    };
  }

  /**
   * UPDATE цены: get-by-id → подставить price → update (остальные поля сохранены).
   */
  async updateItemPrice(id: number, price: number | string): Promise<unknown> {
    const prepared = await this.prepareUpdateItemPrice(id, price);
    return this.updateItem(prepared.payload);
  }

  async updateItem(payload: BrisklyUpdateItemPayload): Promise<unknown> {
    return this.requestJson<unknown>('POST', '/v2/dashboard/item/update', payload);
  }

  /**
   * Категории для CREATE UI (не для фильтра поиска).
   */
  async listCategories(options: { page?: number; limit?: number } = {}): Promise<
    BrisklyCategoryListResponse
  > {
    const page = options.page ?? 1;
    const limit = options.limit ?? 200;
    const qs = buildQueryString({
      page,
      limit,
      fields: {
        id: 'id',
        name: 'name',
        catalog_id: 'catalog_id',
        parent_id: 'parent_id',
        status: 'status',
      },
    });
    const raw = await this.requestJson<BrisklyCategoryListResponse>(
      'GET',
      `/v2/dashboard/category/get-list?${qs}`,
    );
    return {
      items: Array.isArray(raw.items) ? raw.items : [],
      meta: raw.meta,
    };
  }

  async listAllCategories(options: { limit?: number; delayMs?: number } = {}): Promise<
    BrisklyCategory[]
  > {
    const limit = options.limit ?? 200;
    const delayMs = options.delayMs ?? this.delayBetweenRequestsMs;
    const all: BrisklyCategory[] = [];
    let page = 1;
    let totalPages = 1;

    do {
      const response = await this.listCategories({ page, limit });
      all.push(...response.items);
      totalPages = response.meta?.total_pages ?? page;
      page += 1;
      if (page <= totalPages) {
        await sleep(delayMs);
      }
    } while (page <= totalPages);

    return all;
  }

  /** Собирает create payload без записи. */
  prepareCreateItem(input: CreateItemInput): BrisklyCreateItemPayload {
    return buildCreateItemPayload(input);
  }

  /**
   * CREATE товара (v1). Имя+цена из source, category_id от пользователя.
   */
  async createItem(input: CreateItemInput): Promise<unknown> {
    const payload = this.prepareCreateItem(input);
    return this.requestJson<unknown>('POST', '/v1/dashboard/item/create', payload);
  }

  private async requestJson<T>(
    method: 'GET' | 'POST',
    path: string,
    body?: unknown,
  ): Promise<T> {
    const url = `${this.baseUrl}${path.startsWith('/') ? path : `/${path}`}`;
    const headers: Record<string, string> = {
      Accept: 'application/json, text/plain, */*',
      Authorization: `Bearer ${this.token}`,
      'Accept-Language': 'ru',
    };

    const init: RequestInit = {
      method,
      headers,
    };

    if (method === 'POST') {
      headers['Content-Type'] = 'application/json;charset=utf-8';
      init.body = JSON.stringify(body ?? {});
    }

    const response = await this.fetchImpl(url, init);
    if (!response.ok) {
      // Не включаем тело ответа целиком — может содержать лишние данные; без token.
      throw new BrisklyApiError(
        `Briskly HTTP ${response.status} for ${method} ${path.split('?')[0]}`,
        response.status,
        path.split('?')[0] ?? path,
      );
    }

    const text = await response.text();
    if (!text) {
      return {} as T;
    }

    try {
      return JSON.parse(text) as T;
    } catch {
      throw new BrisklyApiError(
        `Briskly invalid JSON for ${method} ${path.split('?')[0]}`,
        response.status,
        path.split('?')[0] ?? path,
      );
    }
  }
}
