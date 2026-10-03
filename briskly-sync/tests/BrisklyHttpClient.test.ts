import { describe, expect, it, vi } from 'vitest';
import { BrisklyHttpClient } from '../src/client/BrisklyHttpClient.js';
import { buildItemGetListParams, buildQueryString } from '../src/client/query.js';

describe('BrisklyHttpClient', () => {
  it('listItems: GET get-list без category filter + client search', async () => {
    const fetchImpl = vi.fn(async (input: RequestInfo | URL) => {
      const url = String(input);
      expect(url).toContain('/v2/dashboard/item/get-list?');
      expect(url).not.toMatch(/filters%5Bcategory_id/);
      return new Response(
        JSON.stringify({
          items: [
            { id: 1, name: 'Суп', price: 100, category_id: 9, catalog_id: 1 },
            { id: 2, name: 'Рис', price: 80, category_id: 9, catalog_id: 1 },
          ],
          meta: { total_pages: 1 },
        }),
        { status: 200, headers: { 'Content-Type': 'application/json' } },
      );
    });

    const client = new BrisklyHttpClient({
      token: 'test-token',
      fetchImpl: fetchImpl as unknown as typeof fetch,
      delayBetweenRequestsMs: 0,
    });

    const result = await client.listItems({ searchText: 'рис' });
    expect(result.items).toHaveLength(1);
    expect(result.items[0]?.name).toBe('Рис');

    const auth = (fetchImpl.mock.calls[0]?.[1] as RequestInit | undefined)?.headers as
      | Record<string, string>
      | undefined;
    expect(auth?.Authorization).toBe('Bearer test-token');
  });

  it('searchItems: листает страницы пока не наберёт maxResults', async () => {
    const pages: Record<number, unknown> = {
      1: {
        items: [
          { id: 1, name: 'Хлеб', price: 10 },
          { id: 2, name: 'Рис', price: 20 },
        ],
        meta: { total_pages: 3, total_items: 6 },
      },
      2: {
        items: [
          { id: 3, name: 'Суп куриный', price: 100 },
          { id: 4, name: 'Салат', price: 50 },
        ],
        meta: { total_pages: 3, total_items: 6 },
      },
      3: {
        items: [
          { id: 5, name: 'Суп гороховый', price: 90 },
          { id: 6, name: 'Суп том ям', price: 120 },
        ],
        meta: { total_pages: 3, total_items: 6 },
      },
    };

    const fetchImpl = vi.fn(async (input: RequestInfo | URL) => {
      const url = new URL(String(input));
      const page = Number(url.searchParams.get('page') ?? '1');
      return new Response(JSON.stringify(pages[page]), { status: 200 });
    });

    const client = new BrisklyHttpClient({
      token: 'tok',
      fetchImpl: fetchImpl as unknown as typeof fetch,
      delayBetweenRequestsMs: 0,
    });

    const result = await client.searchItems({
      searchText: 'суп',
      maxResults: 2,
      pageSize: 2,
    });

    expect(result.items).toHaveLength(2);
    expect(result.items.map((i) => i.id)).toEqual([3, 5]);
    expect(result.pagesScanned).toBe(3);
    expect(result.totalPages).toBe(3);
    expect(result.totalItems).toBe(6);
    expect(result.truncated).toBe(true);
    expect(result.matched).toBeGreaterThanOrEqual(2);
  });

  it('prepareUpdateItemPrice: get-by-id + merge, без POST update', async () => {
    const fetchImpl = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      expect(url).toContain('/v1/dashboard/item/get-by-id?');
      expect(init?.method ?? 'GET').toBe('GET');
      return new Response(
        JSON.stringify({
          id: 42,
          name: 'Салат',
          catalog_id: 3,
          category_id: 8,
          price: 90,
          barcode: 'b1',
          status: 1,
        }),
        { status: 200 },
      );
    });

    const client = new BrisklyHttpClient({
      token: 'tok',
      fetchImpl: fetchImpl as unknown as typeof fetch,
    });

    const prepared = await client.prepareUpdateItemPrice(42, 110);
    expect(prepared.oldPrice).toBe(90);
    expect(prepared.newPrice).toBe(110);
    expect(prepared.payload.price).toBe(110);
    expect(prepared.payload.name).toBe('Салат');
    expect(prepared.payload.barcode).toBe('b1');
    expect(fetchImpl).toHaveBeenCalledTimes(1);
  });

  it('updateItemPrice: после merge делает POST /v2/.../update', async () => {
    const fetchImpl = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      if (url.includes('get-by-id')) {
        return new Response(
          JSON.stringify({
            id: 7,
            name: 'Чай',
            catalog_id: 1,
            category_id: 2,
            price: 50,
          }),
          { status: 200 },
        );
      }
      expect(url).toContain('/v2/dashboard/item/update');
      expect(init?.method).toBe('POST');
      const body = JSON.parse(String(init?.body));
      expect(body.price).toBe(55);
      expect(body.id).toBe(7);
      expect(body.name).toBe('Чай');
      return new Response(JSON.stringify({ message: 'ok' }), { status: 200 });
    });

    const client = new BrisklyHttpClient({
      token: 'tok',
      fetchImpl: fetchImpl as unknown as typeof fetch,
    });

    const result = await client.updateItemPrice(7, 55);
    expect(result).toEqual({ message: 'ok' });
    expect(fetchImpl).toHaveBeenCalledTimes(2);
  });

  it('createItem: POST /v1/.../create', async () => {
    const fetchImpl = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      expect(String(input)).toContain('/v1/dashboard/item/create');
      expect(init?.method).toBe('POST');
      const body = JSON.parse(String(init?.body));
      expect(body.id).toBeUndefined();
      expect(body.name).toBe('Новое');
      expect(body.price).toBe(33);
      expect(body.category_id).toBe(4);
      expect(body.catalog_id).toBe(1);
      return new Response(JSON.stringify({ id: 999 }), { status: 200 });
    });

    const client = new BrisklyHttpClient({
      token: 'tok',
      fetchImpl: fetchImpl as unknown as typeof fetch,
    });

    const result = await client.createItem({
      name: 'Новое',
      price: 33,
      categoryId: 4,
      catalogId: 1,
    });
    expect(result).toEqual({ id: 999 });
  });

  it('listCategories: GET v2 category/get-list', async () => {
    const fetchImpl = vi.fn(async (input: RequestInfo | URL) => {
      expect(String(input)).toContain('/v2/dashboard/category/get-list?');
      return new Response(
        JSON.stringify({ items: [{ id: 1, name: 'Горячее', catalog_id: 2 }], meta: {} }),
        { status: 200 },
      );
    });

    const client = new BrisklyHttpClient({
      token: 'tok',
      fetchImpl: fetchImpl as unknown as typeof fetch,
    });

    const result = await client.listCategories();
    expect(result.items[0]?.name).toBe('Горячее');
  });

  it('query builder для get-list не содержит filters[category_id]', () => {
    const qs = buildQueryString(buildItemGetListParams({ page: 2, limit: 10 }));
    expect(qs).not.toMatch(/filters%5Bcategory_id/);
  });
});
