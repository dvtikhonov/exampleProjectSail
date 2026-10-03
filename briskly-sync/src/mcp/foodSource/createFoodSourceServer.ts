import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import {
  FoodSourceHttpClient,
  resolveFoodSourceConfigFromEnv,
} from './foodSourceHttpClient.js';

function textResult(payload: unknown) {
  return {
    content: [{ type: 'text' as const, text: JSON.stringify(payload, null, 2) }],
  };
}

function errorResult(message: string) {
  return {
    content: [{ type: 'text' as const, text: message }],
    isError: true as const,
  };
}

/**
 * MCP food-source: чтение source-линий VPS через service-c.
 */
export function createFoodSourceServer(
  client: FoodSourceHttpClient = new FoodSourceHttpClient(resolveFoodSourceConfigFromEnv()),
): McpServer {
  const server = new McpServer({
    name: 'food-source',
    version: '0.1.0',
  });

  server.registerTool(
    'list_source_lines',
    {
      title: 'List VPS source menu lines',
      description:
        'Читает source-линии VPS для синка (service-c admin). session_id или restaurant_id.',
      inputSchema: z.object({
        session_id: z.string().optional().describe('ID сессии briskly-sync (Part 4)'),
        restaurant_id: z.number().int().positive().optional(),
        vps_category_id: z.number().int().positive().optional().nullable(),
        search_text: z.string().max(120).optional().nullable(),
      }),
    },
    async (args) => {
      try {
        const data = await client.listSourceLines({
          sessionId: args.session_id,
          restaurantId: args.restaurant_id,
          vpsCategoryId: args.vps_category_id ?? null,
          searchText: args.search_text ?? null,
        });
        return textResult(data);
      } catch (err) {
        return errorResult(err instanceof Error ? err.message : String(err));
      }
    },
  );

  return server;
}
