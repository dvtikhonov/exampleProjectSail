import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import { BrisklyHttpClient } from '../../client/BrisklyHttpClient.js';

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

function clientFromEnv(): BrisklyHttpClient {
  const token = (process.env.BRISKLY_TOKEN ?? '').trim();
  if (!token) {
    throw new Error('BRISKLY_TOKEN is required for briskly-target MCP');
  }
  return new BrisklyHttpClient({
    token,
    baseUrl: process.env.BRISKLY_BASE_URL,
  });
}

/**
 * MCP briskly-target: порт логики changePrices.php + create/categories.
 * category_ids в фильтре поиска не используются.
 */
export function createBrisklyTargetServer(
  client: BrisklyHttpClient = clientFromEnv(),
): McpServer {
  const server = new McpServer({
    name: 'briskly-target',
    version: '0.1.0',
  });

  server.registerTool(
    'list_items',
    {
      title: 'List Briskly items',
      description:
        'Список позиций Briskly (get-list). Без category filter. search_text — client-side.',
      inputSchema: z.object({
        search_text: z.string().optional().nullable(),
        page: z.number().int().positive().optional(),
        limit: z.number().int().positive().max(200).optional(),
      }),
    },
    async (args) => {
      try {
        const data = await client.listItems({
          searchText: args.search_text,
          page: args.page,
          limit: args.limit,
          excludeCategoryFilter: true,
        });
        return textResult(data);
      } catch (err) {
        return errorResult(err instanceof Error ? err.message : String(err));
      }
    },
  );

  server.registerTool(
    'get_item',
    {
      title: 'Get Briskly item by id',
      description: 'GET get-by-id',
      inputSchema: z.object({
        id: z.number().int().positive(),
      }),
    },
    async (args) => {
      try {
        return textResult(await client.getItem(args.id));
      } catch (err) {
        return errorResult(err instanceof Error ? err.message : String(err));
      }
    },
  );

  server.registerTool(
    'update_item_price',
    {
      title: 'Update Briskly item price',
      description: 'get-by-id + update с сохранением остальных полей',
      inputSchema: z.object({
        id: z.number().int().positive(),
        price: z.union([z.number(), z.string()]),
        dry_run: z.boolean().optional().describe('true = только payload, без POST'),
      }),
    },
    async (args) => {
      try {
        if (args.dry_run) {
          return textResult(await client.prepareUpdateItemPrice(args.id, args.price));
        }
        return textResult(await client.updateItemPrice(args.id, args.price));
      } catch (err) {
        return errorResult(err instanceof Error ? err.message : String(err));
      }
    },
  );

  server.registerTool(
    'list_categories',
    {
      title: 'List Briskly categories',
      description: 'Категории для CREATE UI (не для фильтра поиска)',
      inputSchema: z.object({
        page: z.number().int().positive().optional(),
        limit: z.number().int().positive().max(500).optional(),
      }),
    },
    async (args) => {
      try {
        return textResult(await client.listCategories({ page: args.page, limit: args.limit }));
      } catch (err) {
        return errorResult(err instanceof Error ? err.message : String(err));
      }
    },
  );

  server.registerTool(
    'create_item',
    {
      title: 'Create Briskly item',
      description: 'CREATE: name+price из source + category_id пользователя',
      inputSchema: z.object({
        name: z.string().min(1),
        price: z.union([z.number(), z.string()]),
        category_id: z.number().int().positive(),
        catalog_id: z.number().int().positive(),
        dry_run: z.boolean().optional(),
      }),
    },
    async (args) => {
      try {
        const input = {
          name: args.name,
          price: args.price,
          categoryId: args.category_id,
          catalogId: args.catalog_id,
        };
        if (args.dry_run) {
          return textResult(client.prepareCreateItem(input));
        }
        return textResult(await client.createItem(input));
      } catch (err) {
        return errorResult(err instanceof Error ? err.message : String(err));
      }
    },
  );

  return server;
}
