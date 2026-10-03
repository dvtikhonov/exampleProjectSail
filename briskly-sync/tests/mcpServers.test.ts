import { describe, expect, it, vi } from 'vitest';
import { FoodSourceHttpClient } from '../src/mcp/foodSource/foodSourceHttpClient.js';
import { createFoodSourceServer } from '../src/mcp/foodSource/createFoodSourceServer.js';
import { createBrisklyTargetServer } from '../src/mcp/brisklyTarget/createBrisklyTargetServer.js';
import { BrisklyHttpClient } from '../src/client/BrisklyHttpClient.js';

describe('MCP food-source', () => {
  it('list_source_lines ходит в service-c source-lines', async () => {
    const fetchImpl = vi.fn(async (input: RequestInfo | URL) => {
      const url = String(input);
      expect(url).toContain('/source-lines?');
      expect(url).toContain('restaurant_id=7');
      expect(url).toContain('search_text=%D1%81%D1%83%D0%BF');
      return new Response(
        JSON.stringify({
          source_lines: [
            {
              line_key: 'single:1',
              type: 'single',
              display_name: 'Суп',
              price: '90.00',
              part_dish_ids: [1],
            },
          ],
        }),
        { status: 200 },
      );
    });

    const client = new FoodSourceHttpClient({
      baseUrl: 'http://service-c.test/api/food/admin/briskly-sync',
      authToken: 'admin-token',
      fetchImpl: fetchImpl as unknown as typeof fetch,
    });

    const data = (await client.listSourceLines({
      restaurantId: 7,
      searchText: 'суп',
    })) as { source_lines: unknown[] };

    expect(data.source_lines).toHaveLength(1);
    const auth = (fetchImpl.mock.calls[0]?.[1] as RequestInit)?.headers as Record<string, string>;
    expect(auth.Authorization).toBe('Bearer admin-token');

    // Server registers tool without throwing.
    expect(() => createFoodSourceServer(client)).not.toThrow();
  });
});

describe('MCP briskly-target', () => {
  it('createBrisklyTargetServer регистрируется на клиенте', () => {
    const client = new BrisklyHttpClient({
      token: 'tok',
      fetchImpl: (async () => new Response('{}', { status: 200 })) as unknown as typeof fetch,
      delayBetweenRequestsMs: 0,
    });
    expect(() => createBrisklyTargetServer(client)).not.toThrow();
  });
});
