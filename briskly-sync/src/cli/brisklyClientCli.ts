#!/usr/bin/env node
/**
 * CLI для проверки Briskly HTTP-клиента.
 *
 * Env:
 *   BRISKLY_TOKEN — Bearer JWT (обязателен)
 *   BRISKLY_BASE_URL — опционально (default https://briskly.business/api/company)
 *
 * Команды:
 *   list [--search TEXT] [--page N] [--limit N]
 *   snapshot [--search TEXT] [--limit N]
 *   get <id>
 *   update-price <id> <price> [--apply]   (по умолчанию dry-run)
 *   categories [--page N] [--limit N]
 *   create --name NAME --price PRICE --category-id ID --catalog-id ID [--apply]
 */

import { BrisklyHttpClient } from '../client/BrisklyHttpClient.js';
import { DEFAULT_BRISKLY_BASE_URL } from '../config.js';
import { loadPackageEnv } from './loadEnv.js';

loadPackageEnv();

function printHelp(): void {
  console.log(`Usage: npm run cli -- <command> [options]

Commands:
  list [--search TEXT] [--page N] [--limit N]
  snapshot [--search TEXT] [--limit N]
  get <id>
  update-price <id> <price> [--apply]
  categories [--page N] [--limit N]
  create --name NAME --price PRICE --category-id ID --catalog-id ID [--apply]

Env: BRISKLY_TOKEN (required), BRISKLY_BASE_URL (optional)
Token: export BRISKLY_TOKEN=...  или файл briskly-sync/.env (см. .env.example)
--search: обходит страницы каталога (API не фильтрует по имени), --limit = max совпадений
Default update/create: dry-run (print payload, no write). Pass --apply to POST.
`);
}

function getFlag(args: string[], name: string): string | undefined {
  const idx = args.indexOf(name);
  if (idx === -1) {
    return undefined;
  }
  return args[idx + 1];
}

function hasFlag(args: string[], name: string): boolean {
  return args.includes(name);
}

function requireToken(): string {
  const token = process.env.BRISKLY_TOKEN?.trim();
  if (!token) {
    console.error(
      [
        'BRISKLY_TOKEN is required.',
        'Задайте токен одним из способов:',
        '  export BRISKLY_TOKEN=\'ваш_jwt\'',
        '  или: cp .env.example .env  &&  впишите BRISKLY_TOKEN=... в .env',
        'Токен: кабинет briskly.business → DevTools → Network → Authorization: Bearer …',
      ].join('\n'),
    );
    process.exit(1);
  }
  return token;
}

function createClient(): BrisklyHttpClient {
  return new BrisklyHttpClient({
    token: requireToken(),
    baseUrl: process.env.BRISKLY_BASE_URL?.trim() || DEFAULT_BRISKLY_BASE_URL,
  });
}

function redactSecrets(value: unknown): unknown {
  // На всякий случай не печатаем строки похожие на JWT
  if (typeof value === 'string' && value.startsWith('eyJ')) {
    return '[redacted]';
  }
  return value;
}

async function main(): Promise<void> {
  const args = process.argv.slice(2);
  const command = args[0];

  if (!command || command === 'help' || command === '--help' || command === '-h') {
    printHelp();
    process.exit(command ? 0 : 1);
  }

  const client = createClient();

  switch (command) {
    case 'list': {
      const page = Number(getFlag(args, '--page') ?? 1);
      const limit = Number(getFlag(args, '--limit') ?? 50);
      const search = getFlag(args, '--search') ?? null;

      if (search?.trim()) {
        // API не фильтрует по имени — обходим страницы, пока не наберём limit совпадений.
        const result = await client.searchItems({
          searchText: search,
          maxResults: limit,
        });
        console.log(
          JSON.stringify(
            {
              count: result.items.length,
              matched_before_cap: result.matched,
              pages_scanned: result.pagesScanned,
              total_pages: result.totalPages,
              total_items: result.totalItems,
              truncated: result.truncated,
              search: search.trim(),
              items: result.items.map((item) => ({
                id: item.id,
                name: item.name,
                price: item.price,
                category_id: item.category_id,
                catalog_id: item.catalog_id,
              })),
            },
            null,
            2,
          ),
        );
        break;
      }

      const result = await client.listItems({ page, limit });
      console.log(
        JSON.stringify(
          {
            count: result.items.length,
            meta: result.meta,
            items: result.items.map((item) => ({
              id: item.id,
              name: item.name,
              price: item.price,
              category_id: item.category_id,
              catalog_id: item.catalog_id,
            })),
          },
          null,
          2,
        ),
      );
      break;
    }
    case 'snapshot': {
      const limit = Number(getFlag(args, '--limit') ?? 50);
      const search = getFlag(args, '--search') ?? null;
      const items = await client.listItemsSnapshot({ limit, searchText: search });
      console.log(
        JSON.stringify(
          {
            count: items.length,
            items: items.map((item) => ({
              id: item.id,
              name: item.name,
              price: item.price,
              category_id: item.category_id,
              catalog_id: item.catalog_id,
            })),
          },
          null,
          2,
        ),
      );
      break;
    }
    case 'get': {
      const id = Number(args[1]);
      if (!Number.isFinite(id)) {
        console.error('get requires numeric <id>');
        process.exit(1);
      }
      const item = await client.getItem(id);
      console.log(JSON.stringify(item, null, 2));
      break;
    }
    case 'update-price': {
      const id = Number(args[1]);
      const price = args[2];
      if (!Number.isFinite(id) || price === undefined) {
        console.error('update-price requires <id> <price>');
        process.exit(1);
      }
      const apply = hasFlag(args, '--apply');
      const prepared = await client.prepareUpdateItemPrice(id, price);
      console.log(
        JSON.stringify(
          {
            dry_run: !apply,
            item_id: prepared.itemId,
            old_price: prepared.oldPrice,
            new_price: prepared.newPrice,
            payload: prepared.payload,
          },
          null,
          2,
        ),
      );
      if (apply) {
        const result = await client.updateItem(prepared.payload);
        console.log(JSON.stringify({ applied: true, result: redactSecrets(result) }, null, 2));
      }
      break;
    }
    case 'categories': {
      const page = Number(getFlag(args, '--page') ?? 1);
      const limit = Number(getFlag(args, '--limit') ?? 200);
      const result = await client.listCategories({ page, limit });
      console.log(
        JSON.stringify(
          {
            count: result.items.length,
            meta: result.meta,
            items: result.items.map((c) => ({
              id: c.id,
              name: c.name,
              catalog_id: c.catalog_id,
              parent_id: c.parent_id,
            })),
          },
          null,
          2,
        ),
      );
      break;
    }
    case 'create': {
      const name = getFlag(args, '--name');
      const price = getFlag(args, '--price');
      const categoryId = Number(getFlag(args, '--category-id'));
      const catalogId = Number(getFlag(args, '--catalog-id'));
      if (!name || price === undefined || !Number.isFinite(categoryId) || !Number.isFinite(catalogId)) {
        console.error(
          'create requires --name --price --category-id --catalog-id',
        );
        process.exit(1);
      }
      const apply = hasFlag(args, '--apply');
      const payload = client.prepareCreateItem({
        name,
        price,
        categoryId,
        catalogId,
      });
      console.log(JSON.stringify({ dry_run: !apply, payload }, null, 2));
      if (apply) {
        const result = await client.createItem({
          name,
          price,
          categoryId,
          catalogId,
        });
        console.log(JSON.stringify({ applied: true, result: redactSecrets(result) }, null, 2));
      }
      break;
    }
    default:
      console.error(`Unknown command: ${command}`);
      printHelp();
      process.exit(1);
  }
}

main().catch((error: unknown) => {
  const message = error instanceof Error ? error.message : String(error);
  // Не печатаем stack с потенциальными заголовками/токеном.
  console.error(message);
  process.exit(1);
});
