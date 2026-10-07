import { spawnSync } from 'node:child_process';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';
import { loadPackageEnv } from '../src/cli/loadEnv.js';
import {
  LLM_PING_EXPECTED,
  LLM_PING_PROMPT,
  isLlmPingOk,
  normalizeLlmPingReply,
} from '../src/orchestrator/pingLlmReply.js';

loadPackageEnv();

const packageRoot = join(dirname(fileURLToPath(import.meta.url)), '..');
const hasCursorApiKey = Boolean(process.env.CURSOR_API_KEY?.trim());

describe('LLM ping reply contract', () => {
  it('промпт требует ровно одно слово PONG', () => {
    expect(LLM_PING_PROMPT).toContain(LLM_PING_EXPECTED);
    expect(LLM_PING_PROMPT.toLowerCase()).toContain('exactly');
  });

  it('normalize снимает пробелы и кавычки', () => {
    expect(normalizeLlmPingReply('  PONG  ')).toBe('PONG');
    expect(normalizeLlmPingReply('"PONG"')).toBe('PONG');
    expect(normalizeLlmPingReply("'pong'")).toBe('pong');
  });

  it('isLlmPingOk принимает PONG без учёта регистра', () => {
    expect(isLlmPingOk('PONG')).toBe(true);
    expect(isLlmPingOk('pong')).toBe(true);
    expect(isLlmPingOk('`PONG`')).toBe(true);
    expect(isLlmPingOk('YES')).toBe(false);
    expect(isLlmPingOk('PONG!')).toBe(false);
  });
});

describe('live LLM ping (Cursor SDK)', () => {
  it.skipIf(!hasCursorApiKey)(
    'Agent.create+send отвечает ровно PONG',
    () => {
      // Subprocess: @cursor/sdk + sqlite иначе роняют vitest worker после dispose.
      const result = spawnSync(
        'bash',
        ['scripts/run-with-node22.sh', 'npx', 'tsx', 'scripts/llm-ping-once.ts'],
        {
          cwd: packageRoot,
          encoding: 'utf8',
          timeout: 90_000,
          env: process.env,
        },
      );

      expect(result.error, result.stderr || String(result.error)).toBeUndefined();
      expect(result.status, result.stderr || result.stdout).toBe(0);
      expect(result.stdout.trim().split(/\r?\n/).at(-1)).toBe(LLM_PING_EXPECTED);
    },
    100_000,
  );
});
