#!/usr/bin/env node
/**
 * Захват Briskly JWT из уже запущенного Chrome (CDP).
 *
 *   npm run capture-token
 *   npm run capture-token -- --write-env
 *
 * Env: BRISKLY_CDP_URL (default http://127.0.0.1:9222),
 *      BRISKLY_CDP_CAPTURE_TIMEOUT_MS (default 20000).
 *
 * По умолчанию печатает только токен в stdout.
 * --write-env → записывает/обновляет BRISKLY_TOKEN в .env пакета (для CLI/MCP).
 * JWT в stderr/логах не печатается (только token_len).
 */

import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { loadPackageEnv } from './loadEnv.js';
import { writeBrisklyTokenToEnv } from './writeBrisklyTokenToEnv.js';
import {
  BrisklyTokenCaptureError,
  captureBrisklyTokenFromCdp,
} from '../tokenCapture/captureBrisklyTokenFromCdp.js';

loadPackageEnv();

function printHelp(): void {
  console.log(`Usage:
  npm run capture-token
  npm run capture-token -- --write-env

Captures Briskly Bearer JWT from Chrome via CDP (playwright-core connectOverCDP).
Requires Chrome with --remote-debugging-port (see README) and an open briskly.business tab.

Default: print token to stdout only.
--write-env: set BRISKLY_TOKEN in package .env (for CLI / MCP).

Env:
  BRISKLY_CDP_URL                 default http://127.0.0.1:9222
  BRISKLY_CDP_CAPTURE_TIMEOUT_MS  default 20000
`);
}

function packageEnvPath(): string {
  const here = dirname(fileURLToPath(import.meta.url));
  return join(here, '..', '..', '.env');
}

async function main(): Promise<void> {
  const args = process.argv.slice(2);
  if (args.includes('--help')) {
    printHelp();
    process.exit(0);
  }

  const writeEnv = args.includes('--write-env');

  const result = await captureBrisklyTokenFromCdp({
    log: (event, meta) => {
      console.error(JSON.stringify({ event, ...meta }));
    },
  });

  if (writeEnv) {
    const envPath = packageEnvPath();
    writeBrisklyTokenToEnv(envPath, result.token);
    console.error(
      JSON.stringify({
        event: 'write_env_ok',
        path: envPath,
        token_len: result.token.length,
        captured: true,
      }),
    );
    return;
  }

  process.stdout.write(`${result.token}\n`);
}

main().catch((err) => {
  if (err instanceof BrisklyTokenCaptureError) {
    console.error(JSON.stringify({ error: err.code }));
    process.exit(1);
  }
  console.error(err instanceof Error ? err.message : String(err));
  process.exit(1);
});
