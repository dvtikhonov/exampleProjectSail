import { randomUUID } from 'node:crypto';
import { mkdtemp, mkdir, rm, symlink, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { describe, expect, it, vi } from 'vitest';
import type { Run, SDKAgent } from '@cursor/sdk';
import {
  handleAsyncScheduleOcr,
  type AsyncScheduleOcrConfig,
  type AsyncScheduleOcrRequestBody,
} from '../src/scheduleOcr/handleAsyncScheduleOcr.js';
import type { StartedScheduleOcrRun } from '../src/scheduleOcr/runScheduleOcr.js';

const SECRET = 'UNIQUE_OUTSIDE_BYTES_9f3a2c';

function baseBody(photoKey: string, overrides: Record<string, unknown> = {}): AsyncScheduleOcrRequestBody {
  return {
    run_id: randomUUID(),
    photo_key: photoKey,
    date_from: '2026-08-13',
    date_to: '2026-08-19',
    restaurant_id: 1,
    category_ids: [3],
    catalog_scope: [{ id: 10, name: 'Картофельное пюре', category_id: 3, category_name: 'Гарнир' }],
    aliases_markdown: '| пюре | Картофельное пюре |',
    ...overrides,
  } as AsyncScheduleOcrRequestBody;
}

function configWith(
  photoRoot: string,
  extra: Partial<AsyncScheduleOcrConfig> = {},
): AsyncScheduleOcrConfig {
  return {
    callbackUrl: 'http://php.test/ocr-complete',
    captureSecret: 'secret',
    photoRoot,
    handshakeTimeoutMs: 5_000,
    llmTimeoutMs: 60_000,
    ...extra,
  };
}

async function withPhoto(
  run: (photo: { root: string; key: string; bytes: Buffer }) => Promise<void>,
): Promise<void> {
  const root = await mkdtemp(path.join(tmpdir(), 'schedule-ocr-'));
  const key = 'schedule/photo.jpg';
  const bytes = Buffer.from('fake-image');
  await mkdir(path.join(root, 'schedule'));
  await writeFile(path.join(root, key), bytes);
  try {
    await run({ root, key, bytes });
  } finally {
    await rm(root, { recursive: true, force: true });
  }
}

async function withDirs(
  run: (dirs: { root: string; outside: string }) => Promise<void>,
): Promise<void> {
  const parent = await mkdtemp(path.join(tmpdir(), 'schedule-ocr-jail-'));
  const root = path.join(parent, 'root');
  const outside = path.join(parent, 'outside');
  await mkdir(root);
  await mkdir(outside);
  try {
    await run({ root, outside });
  } finally {
    await rm(parent, { recursive: true, force: true });
  }
}

function stubAgent(): SDKAgent {
  return {
    agentId: 'agent-test',
    model: undefined,
    send: async () => stubRun(),
    close: () => undefined,
    reload: async () => undefined,
    [Symbol.asyncDispose]: async () => undefined,
    listArtifacts: async () => [],
    downloadArtifact: async () => Buffer.alloc(0),
    getUsage: async () => ({ totalCents: 0, runs: [] }),
  } as unknown as SDKAgent;
}

function stubRun(): Run {
  return {
    id: 'run-test',
    agentId: 'agent-test',
    status: 'running',
    supports: () => true,
    unsupportedReason: () => undefined,
    stream: async function* () {},
    conversation: async () => [],
    wait: async () => ({ id: 'run-test', status: 'finished' as const }),
    steer: async () => ({ outcome: 'ok' as const }),
    cancel: async () => undefined,
    onDidChangeStatus: () => () => undefined,
  } as unknown as Run;
}

describe('handleAsyncScheduleOcr', () => {
  it('fixture llm_response → 202 и callback с entries', async () => {
    await withPhoto(async ({ root, key }) => {
      const callbacks: unknown[] = [];
      const backgroundTasks: Array<() => Promise<void>> = [];
      const runId = randomUUID();

      const config = configWith(root, {
        scheduleBackground: (task) => {
          backgroundTasks.push(task);
        },
        fetchImpl: (async (_url, init) => {
          callbacks.push(JSON.parse(String(init?.body)));
          return new Response(JSON.stringify({ ok: true }), { status: 200 });
        }) as typeof fetch,
      });

      const result = await handleAsyncScheduleOcr(
        baseBody(key, {
          run_id: runId,
          llm_response: JSON.stringify({
            entries: [{ raw_title: 'Картофельное пюре', dates: ['2026-08-13'] }],
            discarded: [],
          }),
        }),
        config,
      );

      expect(result).toEqual({ ok: true, status: 202, body: { accepted: true, phase: 'running' } });
      expect(backgroundTasks).toHaveLength(1);
      await backgroundTasks[0]!();
      expect(callbacks).toEqual([
        {
          run_id: runId,
          entries: [{ raw_title: 'Картофельное пюре', dates: ['2026-08-13'] }],
          discarded: [],
        },
      ]);
    });
  });

  it('live handshake берёт base64 с диска, а не из тела', async () => {
    await withPhoto(async ({ root, key, bytes }) => {
      const backgroundTasks: Array<() => Promise<void>> = [];
      let sendResolved = false;

      const startScheduleOcrRun = vi.fn(async (): Promise<StartedScheduleOcrRun> => {
        await new Promise((r) => setTimeout(r, 10));
        sendResolved = true;
        return { agent: stubAgent(), run: stubRun() };
      });

      const config = configWith(root, {
        startScheduleOcrRun,
        awaitCursorRun: async () =>
          JSON.stringify({ entries: [], discarded: [{ raw_title: 'х', reason: 'dish_not_found' }] }),
        disposeCursorAgent: async () => undefined,
        scheduleBackground: (task) => {
          backgroundTasks.push(task);
        },
        fetchImpl: (async () =>
          new Response(JSON.stringify({ ok: true }), { status: 200 })) as typeof fetch,
      });

      const result = await handleAsyncScheduleOcr(
        baseBody(key, {
          photo_base64: Buffer.from('not-the-file').toString('base64'),
          photo_path: '/etc/passwd',
          photo_mime_type: 'image/png',
        }),
        config,
      );
      expect(sendResolved).toBe(true);
      expect(result.status).toBe(202);
      expect(startScheduleOcrRun).toHaveBeenCalledWith(expect.objectContaining({
        image: {
          data: bytes.toString('base64'),
          mimeType: 'image/jpeg',
        },
      }));
      expect(backgroundTasks).toHaveLength(1);
      await backgroundTasks[0]!();
    });
  });

  it('capture_secret в JSON не подменяет секрет колбэка', async () => {
    await withPhoto(async ({ root, key }) => {
      const secrets: string[] = [];
      const backgroundTasks: Array<() => Promise<void>> = [];
      const config = configWith(root, {
        captureSecret: 'from-env',
        scheduleBackground: (task) => {
          backgroundTasks.push(task);
        },
        fetchImpl: (async (_url, init) => {
          const headers = new Headers(init?.headers);
          secrets.push(headers.get('X-Schedule-Photo-Capture-Secret') ?? '');
          return new Response(JSON.stringify({ ok: true }), { status: 200 });
        }) as typeof fetch,
      });

      const result = await handleAsyncScheduleOcr(
        baseBody(key, {
          llm_response: JSON.stringify({ entries: [], discarded: [] }),
          capture_secret: 'from-body',
        }),
        config,
      );

      expect(result.status).toBe(202);
      await backgroundTasks[0]!();
      expect(secrets).toEqual(['from-env']);
    });
  });

  it('400 без run_id uuid', async () => {
    const result = await handleAsyncScheduleOcr(
      baseBody('schedule/photo.jpg', { run_id: 'not-uuid' }),
      configWith('/tmp/unused-photo-root'),
    );
    expect(result.ok).toBe(false);
    expect(result.status).toBe(400);
  });

  it('пустой корень — 503', async () => {
    const result = await handleAsyncScheduleOcr(
      baseBody('schedule/photo.jpg'),
      configWith(''),
    );
    expect(result).toEqual({
      ok: false,
      status: 503,
      body: {
        error: 'photo_root_unconfigured',
        message: 'SCHEDULE_PHOTO_PHOTO_ROOT is required',
      },
    });
  });

  it('абсолютный photo_key — 400 без содержимого файла', async () => {
    await withDirs(async ({ root, outside }) => {
      const file = path.join(outside, 'secret.jpg');
      await writeFile(file, Buffer.from(SECRET));

      const result = await handleAsyncScheduleOcr(baseBody(file), configWith(root));

      expect(result.ok).toBe(false);
      expect(result.status).toBe(400);
      expect(JSON.stringify(result)).not.toContain(SECRET);
    });
  });

  it('photo_key с .. — 400 без содержимого файла', async () => {
    await withDirs(async ({ root, outside }) => {
      await writeFile(path.join(outside, 'secret.jpg'), Buffer.from(SECRET));
      const key = path.relative(root, path.join(outside, 'secret.jpg'));

      const result = await handleAsyncScheduleOcr(baseBody(key), configWith(root));

      expect(result.ok).toBe(false);
      expect(result.status).toBe(400);
      expect(JSON.stringify(result)).not.toContain(SECRET);
    });
  });

  it('симлинк наружу — 400 без содержимого файла', async () => {
    await withDirs(async ({ root, outside }) => {
      const target = path.join(outside, 'secret.jpg');
      await writeFile(target, Buffer.from(SECRET));
      await symlink(target, path.join(root, 'link.jpg'));

      const result = await handleAsyncScheduleOcr(baseBody('link.jpg'), configWith(root));

      expect(result.ok).toBe(false);
      expect(result.status).toBe(400);
      expect(JSON.stringify(result)).not.toContain(SECRET);
    });
  });
});
