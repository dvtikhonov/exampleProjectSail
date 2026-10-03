#!/usr/bin/env node
/**
 * Минимальный HTTP sidecar для PHP (match / health).
 * Порт: BRISKLY_SYNC_PORT (default 8791).
 *
 * POST /match  body: { prompt, source_lines, briskly_snapshot, use_fixture_response? }
 * GET  /health
 */

import { createServer } from 'node:http';
import { loadPackageEnv } from '../cli/loadEnv.js';
import { runMatch } from '../orchestrator/runMatch.js';
import type {
  BrisklySnapshotItem,
  ComboCatalogPromptDto,
  SourceMenuLine,
} from '../orchestrator/types.js';

loadPackageEnv();

const port = Number(process.env.BRISKLY_SYNC_PORT ?? 8791);
/** 127.0.0.1 — только локально; 0.0.0.0 — доступ из Docker (host.docker.internal). */
const host = process.env.BRISKLY_SYNC_HOST ?? '127.0.0.1';

const server = createServer(async (req, res) => {
  try {
    if (req.method === 'GET' && req.url === '/health') {
      json(res, 200, { ok: true, service: 'briskly-sync' });
      return;
    }

    if (req.method === 'POST' && req.url === '/match') {
      const body = await readBody(req);
      const payload = JSON.parse(body) as {
        prompt: ComboCatalogPromptDto;
        source_lines: SourceMenuLine[];
        briskly_snapshot: BrisklySnapshotItem[];
        /** Offline: готовый ответ LLM (для тестов/без Cursor). */
        llm_response?: string;
        enable_mcp?: boolean;
      };

      const output = await runMatch({
        prompt: payload.prompt,
        sourceLines: payload.source_lines ?? [],
        brisklySnapshot: payload.briskly_snapshot ?? [],
        enableMcp: Boolean(payload.enable_mcp),
        matcher: payload.llm_response
          ? async () => payload.llm_response!
          : undefined,
      });

      json(res, 200, {
        match_lines: output.matchLines,
        sync_results: output.syncResults,
      });
      return;
    }

    json(res, 404, { error: 'not_found' });
  } catch (err) {
    json(res, 500, {
      error: 'internal_error',
      message: err instanceof Error ? err.message : String(err),
    });
  }
});

server.listen(port, host, () => {
  console.error(`briskly-sync sidecar on http://${host}:${port}`);
});

function json(res: import('node:http').ServerResponse, status: number, body: unknown): void {
  const text = JSON.stringify(body);
  res.writeHead(status, {
    'Content-Type': 'application/json; charset=utf-8',
    'Content-Length': Buffer.byteLength(text),
  });
  res.end(text);
}

function readBody(req: import('node:http').IncomingMessage): Promise<string> {
  return new Promise((resolve, reject) => {
    const chunks: Buffer[] = [];
    req.on('data', (c) => chunks.push(Buffer.isBuffer(c) ? c : Buffer.from(c)));
    req.on('end', () => resolve(Buffer.concat(chunks).toString('utf8')));
    req.on('error', reject);
  });
}
