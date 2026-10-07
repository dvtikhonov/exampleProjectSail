#!/usr/bin/env node
/**
 * Минимальный HTTP sidecar для PHP (match handshake / abort / health / capture-token).
 * Порт: BRISKLY_SYNC_PORT (default 8791).
 *
 * POST /match       — handshake create+send → 202; wait/stream в фоне → callback
 * POST /match/abort — глушит success-колбэк generation (SDK cancel нет)
 * POST /capture-token  header: X-Briskly-Capture-Secret
 * GET  /health
 */

import { createServer } from 'node:http';
import { loadPackageEnv } from '../cli/loadEnv.js';
import {
  handleAbortMatch,
  handleAsyncMatch,
  resolveAsyncMatchConfigFromEnv,
} from './handleAsyncMatch.js';
import { matchAbortRegistry } from './matchAbortRegistry.js';
import {
  BrisklyTokenCaptureError,
  captureBrisklyTokenFromCdp,
  type CaptureTokenErrorCode,
} from '../tokenCapture/captureBrisklyTokenFromCdp.js';

loadPackageEnv();

const port = Number(process.env.BRISKLY_SYNC_PORT ?? 8791);
/** 127.0.0.1 — только локально; 0.0.0.0 — доступ из Docker (host.docker.internal). */
const host = process.env.BRISKLY_SYNC_HOST ?? '127.0.0.1';
const CAPTURE_SECRET_HEADER = 'x-briskly-capture-secret';

const asyncMatchConfig = {
  ...resolveAsyncMatchConfigFromEnv(),
  abortRegistry: matchAbortRegistry,
  log: (message: string, meta?: Record<string, unknown>) => {
    console.error(JSON.stringify({ event: message, ...meta }));
  },
};

const server = createServer(async (req, res) => {
  try {
    if (req.method === 'GET' && req.url === '/health') {
      json(res, 200, { ok: true, service: 'briskly-sync' });
      return;
    }

    if (req.method === 'POST' && req.url === '/capture-token') {
      await handleCaptureToken(req, res);
      return;
    }

    if (req.method === 'POST' && req.url === '/match') {
      const body = await readBody(req);
      const payload = JSON.parse(body) as Record<string, unknown>;
      const result = await handleAsyncMatch(payload, asyncMatchConfig);
      json(res, result.status, result.body);
      return;
    }

    if (req.method === 'POST' && req.url === '/match/abort') {
      const body = await readBody(req);
      const payload = JSON.parse(body) as { session_id?: unknown; match_generation?: unknown };
      const result = handleAbortMatch(payload, matchAbortRegistry);
      json(res, result.status, result.body);
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

async function handleCaptureToken(
  req: import('node:http').IncomingMessage,
  res: import('node:http').ServerResponse,
): Promise<void> {
  const configuredSecret = (process.env.BRISKLY_SYNC_CAPTURE_SECRET ?? '').trim();
  if (!configuredSecret) {
    json(res, 503, { error: 'capture_disabled' });
    return;
  }

  const provided = headerValue(req, CAPTURE_SECRET_HEADER);
  if (provided === undefined || provided !== configuredSecret) {
    json(res, 401, { error: 'unauthorized' });
    return;
  }

  try {
    const result = await captureBrisklyTokenFromCdp({
      log: (event, meta) => {
        console.error(JSON.stringify({ event, ...meta }));
      },
    });
    json(res, 200, { token: result.token, source: result.source });
  } catch (err) {
    if (err instanceof BrisklyTokenCaptureError) {
      json(res, statusForCaptureError(err.code), { error: err.code });
      return;
    }
    throw err;
  }
}

function statusForCaptureError(code: CaptureTokenErrorCode): number {
  switch (code) {
    case 'cdp_unavailable':
      return 503;
    case 'no_briskly_tab':
    case 'not_logged_in':
    case 'no_token_observed':
    case 'timeout':
      return 422;
    default:
      return 503;
  }
}

function headerValue(
  req: import('node:http').IncomingMessage,
  name: string,
): string | undefined {
  const raw = req.headers[name];
  if (Array.isArray(raw)) {
    return raw[0];
  }
  return raw;
}

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
