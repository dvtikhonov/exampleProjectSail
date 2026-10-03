import { BrisklyHttpClient } from '../client/BrisklyHttpClient.js';
import { SECTION_RESULT_CAP } from './constants.js';
import { pricesEqual } from './price.js';
import type {
  ApplyRunInput,
  ApplyRunReport,
  PriceDiffItem,
  VpsOnlyCreateItem,
} from './types.js';

/**
 * Детерминированный apply по approvals JSON (без LLM).
 * Цены только из server proposals; клиентский price не принимается.
 * Лимит: max 25 UPDATE + 25 CREATE.
 */
export async function runApply(input: ApplyRunInput): Promise<ApplyRunReport> {
  const dryRun = input.dryRun !== false;
  const report: ApplyRunReport = {
    updated: 0,
    created: 0,
    skipped_unchecked: 0,
    skipped_equal: 0,
    errors: [],
    dry_run: dryRun,
  };

  const priceByKey = indexByLineKey(input.priceUpdates);
  const createByKey = indexByLineKey(input.creates);

  const client = new BrisklyHttpClient({
    token: input.token,
    baseUrl: input.baseUrl,
  });

  let updateCount = 0;
  for (const item of input.approvals.price_updates) {
    if (!item.apply) {
      report.skipped_unchecked += 1;
      continue;
    }
    if (updateCount >= SECTION_RESULT_CAP) {
      report.errors.push({
        line_key: item.line_key,
        message: `UPDATE cap ${SECTION_RESULT_CAP} exceeded`,
      });
      continue;
    }

    const proposal = priceByKey.get(item.line_key);
    if (!proposal) {
      report.errors.push({ line_key: item.line_key, message: 'price_update line_key not in proposals' });
      continue;
    }

    if (pricesEqual(proposal.source_price, proposal.briskly_price)) {
      report.skipped_equal += 1;
      continue;
    }

    try {
      if (dryRun) {
        await client.prepareUpdateItemPrice(proposal.briskly_item_id, proposal.source_price);
      } else {
        await client.updateItemPrice(proposal.briskly_item_id, proposal.source_price);
      }
      report.updated += 1;
      updateCount += 1;
    } catch (err) {
      report.errors.push({
        line_key: item.line_key,
        message: err instanceof Error ? err.message : String(err),
      });
    }
  }

  let createCount = 0;
  for (const item of input.approvals.creates) {
    if (!item.apply) {
      report.skipped_unchecked += 1;
      continue;
    }
    if (createCount >= SECTION_RESULT_CAP) {
      report.errors.push({
        line_key: item.line_key,
        message: `CREATE cap ${SECTION_RESULT_CAP} exceeded`,
      });
      continue;
    }

    const proposal = createByKey.get(item.line_key);
    if (!proposal) {
      report.errors.push({ line_key: item.line_key, message: 'create line_key not in proposals' });
      continue;
    }

    const categoryId = item.briskly_category_id;
    if (categoryId == null || !Number.isFinite(Number(categoryId))) {
      report.errors.push({
        line_key: item.line_key,
        message: 'briskly_category_id required for apply create',
      });
      continue;
    }

    const catalogId = input.defaultCatalogId;
    if (catalogId == null) {
      report.errors.push({
        line_key: item.line_key,
        message: 'defaultCatalogId required for create',
      });
      continue;
    }

    try {
      const createInput = {
        name: proposal.display_name,
        price: proposal.source_price,
        categoryId: Number(categoryId),
        catalogId: Number(catalogId),
      };
      if (dryRun) {
        client.prepareCreateItem(createInput);
      } else {
        await client.createItem(createInput);
      }
      report.created += 1;
      createCount += 1;
    } catch (err) {
      report.errors.push({
        line_key: item.line_key,
        message: err instanceof Error ? err.message : String(err),
      });
    }
  }

  return report;
}

function indexByLineKey<T extends { line_key: string }>(items: T[]): Map<string, T> {
  const map = new Map<string, T>();
  for (const item of items) {
    map.set(item.line_key, item);
  }
  return map;
}

export type { PriceDiffItem, VpsOnlyCreateItem };
