#!/usr/bin/env node
/**
 * Детерминированный apply CLI (без LLM).
 *
 *   npm run apply -- --approvals approvals.json --proposals proposals.json [--apply]
 *
 * По умолчанию dry-run. Нужен BRISKLY_TOKEN.
 */

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { loadPackageEnv } from './loadEnv.js';
import { runApply } from '../orchestrator/runApply.js';
import type {
  ApplyApprovals,
  PriceDiffItem,
  VpsOnlyCreateItem,
} from '../orchestrator/types.js';

loadPackageEnv();

interface ProposalsFile {
  price_updates: PriceDiffItem[];
  creates: VpsOnlyCreateItem[];
  default_catalog_id?: number;
}

function printHelp(): void {
  console.log(`Usage:
  npm run apply -- --approvals approvals.json --proposals proposals.json [--apply]

Default: dry-run. Pass --apply to write to Briskly.
Env: BRISKLY_TOKEN required.
`);
}

function getFlag(args: string[], name: string): string | undefined {
  const idx = args.indexOf(name);
  if (idx === -1) {
    return undefined;
  }
  return args[idx + 1];
}

async function main(): Promise<void> {
  const args = process.argv.slice(2);
  if (args.includes('--help') || args.length === 0) {
    printHelp();
    process.exit(args.length === 0 ? 1 : 0);
  }

  const approvalsPath = getFlag(args, '--approvals');
  const proposalsPath = getFlag(args, '--proposals');
  if (!approvalsPath || !proposalsPath) {
    printHelp();
    process.exit(1);
  }

  const token = (process.env.BRISKLY_TOKEN ?? '').trim();
  if (!token) {
    console.error('BRISKLY_TOKEN is required');
    process.exit(1);
  }

  const approvals = JSON.parse(readFileSync(resolve(approvalsPath), 'utf8')) as ApplyApprovals;
  const proposals = JSON.parse(readFileSync(resolve(proposalsPath), 'utf8')) as ProposalsFile;

  const report = await runApply({
    approvals,
    priceUpdates: proposals.price_updates ?? [],
    creates: proposals.creates ?? [],
    defaultCatalogId: proposals.default_catalog_id,
    dryRun: !args.includes('--apply'),
    token,
    baseUrl: process.env.BRISKLY_BASE_URL,
  });

  console.log(JSON.stringify(report, null, 2));
}

main().catch((err) => {
  console.error(err instanceof Error ? err.message : String(err));
  process.exit(1);
});
