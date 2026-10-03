/**
 * Query helpers for Briskly get-list / get-by-id.
 * Важно: category filter в поиск/snapshot НЕ добавляется (контракт синка).
 */

export interface GetListQueryOptions {
  page: number;
  limit: number;
  /**
   * Если true — как в changePrices.php: только корневые позиции, без coffee.
   * category_id filter никогда не передаём.
   */
  applyDefaultItemFilters?: boolean;
}

/** Сериализует nested query как PHP http_build_query (fields[id]=id, filters[parent_id][0]=0). */
export function buildQueryString(params: Record<string, unknown>): string {
  const parts: string[] = [];

  const append = (key: string, value: unknown): void => {
    if (value === undefined || value === null) {
      return;
    }
    if (Array.isArray(value)) {
      value.forEach((item, index) => {
        append(`${key}[${index}]`, item);
      });
      return;
    }
    if (typeof value === 'object') {
      for (const [childKey, childValue] of Object.entries(value as Record<string, unknown>)) {
        append(`${key}[${childKey}]`, childValue);
      }
      return;
    }
    parts.push(`${encodeURIComponent(key)}=${encodeURIComponent(String(value))}`);
  };

  for (const [key, value] of Object.entries(params)) {
    append(key, value);
  }

  return parts.join('&');
}

export function buildItemGetListParams(options: GetListQueryOptions): Record<string, unknown> {
  const params: Record<string, unknown> = {
    page: options.page,
    limit: options.limit,
    fields: {
      id: 'id',
      category: 'category.name',
      name: 'name',
      price: 'price',
      quantity_calculated: 'quantity_calculated',
      status: 'status',
      catalog_id: 'catalog_id',
      category_id: 'category_id',
    },
  };

  if (options.applyDefaultItemFilters !== false) {
    params.filters = {
      parent_id: ['0'],
      '!catalog.item_type': ['coffee'],
    };
  }

  // Явный запрет: filters.category_id / category_ids не добавлять.
  return params;
}

export function matchesSearchText(name: string, searchText: string | null | undefined): boolean {
  const needle = searchText?.trim().toLowerCase();
  if (!needle) {
    return true;
  }
  return name.toLowerCase().includes(needle);
}
