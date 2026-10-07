import { randomUUID } from 'node:crypto';
import { describe, expect, it, vi } from 'vitest';
import type { Run, SDKAgent } from '@cursor/sdk';
import {
  handleAbortMatch,
  handleAsyncMatch,
  type AsyncMatchConfig,
} from '../src/http/handleAsyncMatch.js';
import { MatchAbortRegistry } from '../src/http/matchAbortRegistry.js';
import type { StartedCursorRun } from '../src/orchestrator/runMatch.js';
import type { ComboCatalogPromptDto } from '../src/orchestrator/types.js';

const prompt: ComboCatalogPromptDto = {
  system: 'system from PHP ENUM',
  user: 'user payload from PromptBuilder',
};

function baseBody(overrides: Record<string, unknown> = {}) {
  return {
    session_id: randomUUID(),
    match_generation: randomUUID(),
    prompt,
    source_lines: [],
    briskly_snapshot: [],
    ...overrides,
  };
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

describe('handleAsyncMatch handshake', () => {
  it('202 только после успешного mock send; wait ещё не закончился', async () => {
    const registry = new MatchAbortRegistry();
    const callbacks: unknown[] = [];
    let sendResolved = false;
    let waitStarted = false;
    let resolveWait!: (text: string) => void;

    const waitPromise = new Promise<string>((resolve) => {
      resolveWait = resolve;
    });

    const startCursorRun = vi.fn(async (): Promise<StartedCursorRun> => {
      await new Promise((r) => setTimeout(r, 20));
      sendResolved = true;
      return { agent: stubAgent(), run: stubRun() };
    });

    const awaitCursorRun = vi.fn(async () => {
      waitStarted = true;
      return waitPromise;
    });

    const backgroundTasks: Array<() => Promise<void>> = [];
    const config: AsyncMatchConfig = {
      callbackUrl: 'http://php.test/match-complete',
      captureSecret: 'secret',
      handshakeTimeoutMs: 5_000,
      llmTimeoutMs: 60_000,
      abortRegistry: registry,
      startCursorRun,
      awaitCursorRun,
      disposeCursorAgent: async () => undefined,
      scheduleBackground: (task) => {
        backgroundTasks.push(task);
      },
      fetchImpl: (async (_url, init) => {
        callbacks.push(JSON.parse(String(init?.body)));
        return new Response(JSON.stringify({ ok: true }), { status: 200 });
      }) as typeof fetch,
    };

    const body = baseBody();
    const result = await handleAsyncMatch(body, config);

    expect(result.status).toBe(202);
    expect(result.body).toEqual({ accepted: true, phase: 'running' });
    expect(sendResolved).toBe(true);
    expect(startCursorRun).toHaveBeenCalledTimes(1);
    // Фон ещё не запущен — wait не начат до schedule
    expect(waitStarted).toBe(false);
    expect(callbacks).toHaveLength(0);

    expect(backgroundTasks).toHaveLength(1);
    const bg = backgroundTasks[0]!();
    // Дать awaitCursorRun стартовать
    await Promise.resolve();
    expect(waitStarted).toBe(true);
    expect(callbacks).toHaveLength(0);

    resolveWait(
      JSON.stringify([
        {
          line_key: 'single:1',
          display_name: 'A',
          compare_name: 'a',
          candidates: [{ id: 1, name: 'A' }],
        },
      ]),
    );
    await bg;

    expect(callbacks).toHaveLength(1);
    expect(callbacks[0]).toMatchObject({
      session_id: body.session_id,
      match_generation: body.match_generation,
    });
    expect((callbacks[0] as { match_lines: unknown[] }).match_lines).toHaveLength(1);
  });

  it('handshake throw → 503, колбэка нет', async () => {
    const registry = new MatchAbortRegistry();
    const callbacks: unknown[] = [];
    const backgroundTasks: Array<() => Promise<void>> = [];

    const result = await handleAsyncMatch(baseBody(), {
      callbackUrl: 'http://php.test/match-complete',
      captureSecret: 'secret',
      handshakeTimeoutMs: 5_000,
      llmTimeoutMs: 60_000,
      abortRegistry: registry,
      startCursorRun: async () => {
        throw new Error('no_network');
      },
      scheduleBackground: (task) => backgroundTasks.push(task),
      fetchImpl: (async (_url, init) => {
        callbacks.push(JSON.parse(String(init?.body)));
        return new Response('{}', { status: 200 });
      }) as typeof fetch,
    });

    expect(result.status).toBe(503);
    expect(result.ok).toBe(false);
    expect((result.body as { message?: string }).message).toContain('no_network');
    expect(backgroundTasks).toHaveLength(0);
    expect(callbacks).toHaveLength(0);
  });

  it('abort глушит success-callback после медленного wait', async () => {
    const registry = new MatchAbortRegistry();
    const callbacks: unknown[] = [];
    let resolveWait!: (text: string) => void;
    const waitPromise = new Promise<string>((resolve) => {
      resolveWait = resolve;
    });
    const backgroundTasks: Array<() => Promise<void>> = [];

    const body = baseBody();
    const result = await handleAsyncMatch(body, {
      callbackUrl: 'http://php.test/match-complete',
      captureSecret: 'secret',
      handshakeTimeoutMs: 5_000,
      llmTimeoutMs: 60_000,
      abortRegistry: registry,
      startCursorRun: async () => ({ agent: stubAgent(), run: stubRun() }),
      awaitCursorRun: async () => waitPromise,
      disposeCursorAgent: async () => undefined,
      scheduleBackground: (task) => backgroundTasks.push(task),
      fetchImpl: (async (_url, init) => {
        callbacks.push(JSON.parse(String(init?.body)));
        return new Response(JSON.stringify({ ok: true }), { status: 200 });
      }) as typeof fetch,
    });

    expect(result.status).toBe(202);

    const abort = handleAbortMatch(
      { session_id: body.session_id, match_generation: body.match_generation },
      registry,
    );
    expect(abort.body.aborted).toBe(true);

    const bg = backgroundTasks[0]!();
    resolveWait(
      JSON.stringify([
        {
          line_key: 'single:1',
          display_name: 'A',
          compare_name: 'a',
          candidates: [],
        },
      ]),
    );
    await bg;

    expect(callbacks).toHaveLength(0);
  });

  it('fixture llm_response → 202 сразу, затем callback с match_lines', async () => {
    const registry = new MatchAbortRegistry();
    const callbacks: unknown[] = [];
    const backgroundTasks: Array<() => Promise<void>> = [];
    const llmResponse = JSON.stringify([
      {
        line_key: 'single:1',
        display_name: 'A',
        compare_name: 'a',
        candidates: [{ id: 10, name: 'A' }],
      },
    ]);

    const body = baseBody({ llm_response: llmResponse });
    const result = await handleAsyncMatch(body, {
      callbackUrl: 'http://php.test/match-complete',
      captureSecret: 'secret',
      handshakeTimeoutMs: 5_000,
      llmTimeoutMs: 60_000,
      abortRegistry: registry,
      startCursorRun: async () => {
        throw new Error('should not call Cursor for fixture');
      },
      scheduleBackground: (task) => backgroundTasks.push(task),
      fetchImpl: (async (_url, init) => {
        const initHeaders = init?.headers as Record<string, string>;
        expect(initHeaders['X-Briskly-Capture-Secret']).toBe('secret');
        callbacks.push(JSON.parse(String(init?.body)));
        return new Response(JSON.stringify({ ok: true }), { status: 200 });
      }) as typeof fetch,
    });

    expect(result.status).toBe(202);
    expect(backgroundTasks).toHaveLength(1);
    await backgroundTasks[0]!();

    expect(callbacks).toHaveLength(1);
    expect(callbacks[0]).toMatchObject({
      session_id: body.session_id,
      match_generation: body.match_generation,
      raw_text: llmResponse,
    });
  });

  it('без callback URL → 503', async () => {
    const result = await handleAsyncMatch(baseBody(), {
      callbackUrl: '',
      captureSecret: 'secret',
      handshakeTimeoutMs: 5_000,
      llmTimeoutMs: 60_000,
      abortRegistry: new MatchAbortRegistry(),
    });
    expect(result.status).toBe(503);
    expect((result.body as { error?: string }).error).toBe('callback_unconfigured');
  });

  it('handshake timeout → 503', async () => {
    const disposeCursorAgent = vi.fn(async () => undefined);
    const result = await handleAsyncMatch(baseBody(), {
      callbackUrl: 'http://php.test/match-complete',
      captureSecret: 'secret',
      handshakeTimeoutMs: 40,
      llmTimeoutMs: 60_000,
      abortRegistry: new MatchAbortRegistry(),
      startCursorRun: async () => {
        await new Promise((r) => setTimeout(r, 200));
        return { agent: stubAgent(), run: stubRun() };
      },
      disposeCursorAgent,
      scheduleBackground: () => {
        throw new Error('background must not run after handshake timeout');
      },
    });

    expect(result.status).toBe(503);
    expect((result.body as { message?: string }).message).toContain('timed out');
    // late start may dispose asynchronously
    await new Promise((r) => setTimeout(r, 250));
    expect(disposeCursorAgent).toHaveBeenCalled();
  });
});
