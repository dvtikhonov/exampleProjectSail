import { assertPhpPromptDto, composeAgentPrompt } from '../orchestrator/buildMatchPrompt.js';
import { parseMatchJson } from '../orchestrator/parseMatchJson.js';
import {
  awaitCursorRun,
  disposeCursorAgent,
  startCursorRun,
  type StartedCursorRun,
} from '../orchestrator/runMatch.js';
import type {
  BrisklySnapshotItem,
  ComboCatalogPromptDto,
  MatchLineResult,
  MatchRunInput,
  SourceMenuLine,
} from '../orchestrator/types.js';
import { withTimeout } from '../orchestrator/withTimeout.js';
import {
  MatchAbortRegistry,
  matchAbortRegistry,
  type MatchAbortEntry,
} from './matchAbortRegistry.js';
import {
  postMatchComplete,
  type MatchCompletePayload,
  type PostMatchCompleteResult,
} from './postMatchComplete.js';

const UUID_RE =
  /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

export interface AsyncMatchRequestBody {
  session_id?: unknown;
  match_generation?: unknown;
  prompt?: ComboCatalogPromptDto;
  source_lines?: SourceMenuLine[];
  briskly_snapshot?: BrisklySnapshotItem[];
  /** Offline: готовый ответ LLM (handshake сразу 202, фон → callback). */
  llm_response?: string;
  enable_mcp?: boolean;
}

export interface AsyncMatchConfig {
  callbackUrl: string;
  captureSecret: string;
  /** Handshake create+send, мс (default 60_000). */
  handshakeTimeoutMs: number;
  /** Watchdog фона wait/stream, мс (default 900_000). */
  llmTimeoutMs: number;
  abortRegistry?: MatchAbortRegistry;
  fetchImpl?: typeof fetch;
  startCursorRun?: typeof startCursorRun;
  awaitCursorRun?: typeof awaitCursorRun;
  disposeCursorAgent?: typeof disposeCursorAgent;
  /** После handshake вызвать фон сразу (тесты могут подменить). */
  scheduleBackground?: (task: () => Promise<void>) => void;
  log?: (message: string, meta?: Record<string, unknown>) => void;
}

export type AsyncMatchHandshakeResult =
  | { ok: true; status: 202; body: { accepted: true; phase: 'running' } }
  | { ok: false; status: 400 | 503; body: { error: string; message?: string } };

/**
 * POST /match: validate → create+send (или fixture) → 202; wait/stream в фоне + callback.
 * Не отвечает 202 до успешного handshake.
 */
export async function handleAsyncMatch(
  rawBody: AsyncMatchRequestBody,
  config: AsyncMatchConfig,
): Promise<AsyncMatchHandshakeResult> {
  const validated = validateMatchBody(rawBody);
  if (!validated.ok) {
    return { ok: false, status: 400, body: { error: 'invalid_body', message: validated.message } };
  }

  const { sessionId, matchGeneration, prompt, sourceLines, brisklySnapshot, llmResponse, enableMcp } =
    validated.value;

  if (!config.callbackUrl.trim()) {
    return {
      ok: false,
      status: 503,
      body: { error: 'callback_unconfigured', message: 'BRISKLY_SYNC_CALLBACK_URL is required' },
    };
  }
  if (!config.captureSecret.trim()) {
    return {
      ok: false,
      status: 503,
      body: {
        error: 'callback_unconfigured',
        message: 'BRISKLY_SYNC_CAPTURE_SECRET is required for match callback',
      },
    };
  }

  const registry = config.abortRegistry ?? matchAbortRegistry;
  const abortEntry = registry.register(sessionId, matchGeneration);

  const matchInput: MatchRunInput = {
    prompt,
    sourceLines,
    brisklySnapshot,
    enableMcp,
  };

  try {
    if (llmResponse !== undefined) {
      scheduleBackground(config, () =>
        runFixtureBackground({
          sessionId,
          matchGeneration,
          llmResponse,
          abortEntry,
          registry,
          config,
        }),
      );
      return { ok: true, status: 202, body: { accepted: true, phase: 'running' } };
    }

    const startFn = config.startCursorRun ?? startCursorRun;
    const disposeFn = config.disposeCursorAgent ?? disposeCursorAgent;
    const agentPrompt = (() => {
      assertPhpPromptDto(prompt);
      return composeAgentPrompt(prompt);
    })();

    const startPromise = startFn(agentPrompt, matchInput);
    let started: StartedCursorRun;
    try {
      started = await withTimeout(startPromise, config.handshakeTimeoutMs, 'match handshake');
    } catch (err) {
      // Таймаут/сбой: если create+send всё же завершится позже — закрыть агента.
      void startPromise
        .then((late) => disposeFn(late.agent))
        .catch(() => undefined);
      throw err;
    }

    scheduleBackground(config, () =>
      runCursorBackground({
        sessionId,
        matchGeneration,
        started,
        abortEntry,
        registry,
        config,
      }),
    );

    return { ok: true, status: 202, body: { accepted: true, phase: 'running' } };
  } catch (err) {
    registry.delete(sessionId, matchGeneration);
    const message = err instanceof Error ? err.message : String(err);
    config.log?.('match_handshake_failed', { sessionId, matchGeneration, message });
    return {
      ok: false,
      status: 503,
      body: { error: 'handshake_failed', message },
    };
  }
}

export interface AbortMatchResult {
  status: 200 | 400;
  body: { ok: boolean; aborted?: boolean; error?: string };
}

export function handleAbortMatch(
  rawBody: { session_id?: unknown; match_generation?: unknown },
  registry: MatchAbortRegistry = matchAbortRegistry,
): AbortMatchResult {
  const sessionId = asNonEmptyString(rawBody.session_id);
  const matchGeneration = asNonEmptyString(rawBody.match_generation);
  if (!sessionId || !matchGeneration) {
    return {
      status: 400,
      body: { ok: false, error: 'session_id and match_generation are required' },
    };
  }

  const aborted = registry.abort(sessionId, matchGeneration);
  return { status: 200, body: { ok: true, aborted } };
}

function scheduleBackground(
  config: AsyncMatchConfig,
  task: () => Promise<void>,
): void {
  const schedule = config.scheduleBackground ?? ((fn) => {
    void fn().catch((err) => {
      config.log?.('match_background_unhandled', {
        message: err instanceof Error ? err.message : String(err),
      });
    });
  });
  schedule(task);
}

async function runFixtureBackground(args: {
  sessionId: string;
  matchGeneration: string;
  llmResponse: string;
  abortEntry: MatchAbortEntry;
  registry: MatchAbortRegistry;
  config: AsyncMatchConfig;
}): Promise<void> {
  const { sessionId, matchGeneration, llmResponse, abortEntry, registry, config } = args;
  try {
    if (abortEntry.aborted) {
      return;
    }
    const matchLines = parseMatchJson(llmResponse);
    await sendSuccessOrSkip({
      sessionId,
      matchGeneration,
      matchLines,
      rawText: llmResponse,
      abortEntry,
      config,
    });
  } catch (err) {
    await sendErrorOrSkip({
      sessionId,
      matchGeneration,
      error: err instanceof Error ? err.message : String(err),
      abortEntry,
      config,
    });
  } finally {
    registry.delete(sessionId, matchGeneration);
  }
}

async function runCursorBackground(args: {
  sessionId: string;
  matchGeneration: string;
  started: StartedCursorRun;
  abortEntry: MatchAbortEntry;
  registry: MatchAbortRegistry;
  config: AsyncMatchConfig;
}): Promise<void> {
  const { sessionId, matchGeneration, started, abortEntry, registry, config } = args;
  const awaitFn = config.awaitCursorRun ?? awaitCursorRun;
  const disposeFn = config.disposeCursorAgent ?? disposeCursorAgent;

  try {
    const rawText = await withTimeout(
      awaitFn(started.run),
      config.llmTimeoutMs,
      'match llm wait',
    );

    if (abortEntry.aborted) {
      config.log?.('match_callback_suppressed', { sessionId, matchGeneration, reason: 'aborted' });
      return;
    }

    const matchLines = parseMatchJson(rawText);
    await sendSuccessOrSkip({
      sessionId,
      matchGeneration,
      matchLines,
      rawText,
      abortEntry,
      config,
    });
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    await sendErrorOrSkip({
      sessionId,
      matchGeneration,
      error: message.slice(0, 2000),
      abortEntry,
      config,
    });
  } finally {
    await disposeFn(started.agent);
    registry.delete(sessionId, matchGeneration);
  }
}

async function sendSuccessOrSkip(args: {
  sessionId: string;
  matchGeneration: string;
  matchLines: MatchLineResult[];
  rawText: string;
  abortEntry: MatchAbortEntry;
  config: AsyncMatchConfig;
}): Promise<void> {
  if (args.abortEntry.aborted) {
    args.config.log?.('match_callback_suppressed', {
      sessionId: args.sessionId,
      matchGeneration: args.matchGeneration,
      reason: 'aborted',
    });
    return;
  }

  const payload: MatchCompletePayload = {
    session_id: args.sessionId,
    match_generation: args.matchGeneration,
    match_lines: args.matchLines,
    raw_text: args.rawText,
  };
  const result = await postCallback(args.config, payload);
  args.config.log?.('match_callback_sent', {
    sessionId: args.sessionId,
    matchGeneration: args.matchGeneration,
    status: result.status,
    ok: result.ok,
  });
}

async function sendErrorOrSkip(args: {
  sessionId: string;
  matchGeneration: string;
  error: string;
  abortEntry: MatchAbortEntry;
  config: AsyncMatchConfig;
}): Promise<void> {
  if (args.abortEntry.aborted) {
    args.config.log?.('match_callback_suppressed', {
      sessionId: args.sessionId,
      matchGeneration: args.matchGeneration,
      reason: 'aborted_error',
    });
    return;
  }

  const payload: MatchCompletePayload = {
    session_id: args.sessionId,
    match_generation: args.matchGeneration,
    error: args.error,
  };
  const result = await postCallback(args.config, payload);
  args.config.log?.('match_callback_error_sent', {
    sessionId: args.sessionId,
    matchGeneration: args.matchGeneration,
    status: result.status,
    ok: result.ok,
  });
}

async function postCallback(
  config: AsyncMatchConfig,
  payload: MatchCompletePayload,
): Promise<PostMatchCompleteResult> {
  try {
    return await postMatchComplete({
      callbackUrl: config.callbackUrl,
      captureSecret: config.captureSecret,
      payload,
      fetchImpl: config.fetchImpl,
    });
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    config.log?.('match_callback_failed', { message });
    return { ok: false, status: 0, body: message };
  }
}

type ValidatedMatchBody =
  | {
      ok: true;
      value: {
        sessionId: string;
        matchGeneration: string;
        prompt: ComboCatalogPromptDto;
        sourceLines: SourceMenuLine[];
        brisklySnapshot: BrisklySnapshotItem[];
        llmResponse?: string;
        enableMcp: boolean;
      };
    }
  | { ok: false; message: string };

function validateMatchBody(body: AsyncMatchRequestBody): ValidatedMatchBody {
  const sessionId = asNonEmptyString(body.session_id);
  const matchGeneration = asNonEmptyString(body.match_generation);
  if (!sessionId || !isUuid(sessionId)) {
    return { ok: false, message: 'session_id must be a uuid' };
  }
  if (!matchGeneration || !isUuid(matchGeneration)) {
    return { ok: false, message: 'match_generation must be a uuid' };
  }
  if (!body.prompt || typeof body.prompt !== 'object') {
    return { ok: false, message: 'prompt is required' };
  }
  try {
    assertPhpPromptDto(body.prompt);
  } catch (err) {
    return { ok: false, message: err instanceof Error ? err.message : String(err) };
  }

  const llmResponse =
    typeof body.llm_response === 'string' && body.llm_response.trim() !== ''
      ? body.llm_response
      : undefined;

  return {
    ok: true,
    value: {
      sessionId,
      matchGeneration,
      prompt: body.prompt,
      sourceLines: Array.isArray(body.source_lines) ? body.source_lines : [],
      brisklySnapshot: Array.isArray(body.briskly_snapshot) ? body.briskly_snapshot : [],
      llmResponse,
      enableMcp: Boolean(body.enable_mcp),
    },
  };
}

function asNonEmptyString(value: unknown): string | undefined {
  if (typeof value !== 'string') {
    return undefined;
  }
  const trimmed = value.trim();
  return trimmed !== '' ? trimmed : undefined;
}

function isUuid(value: string): boolean {
  return UUID_RE.test(value);
}

export function resolveAsyncMatchConfigFromEnv(
  env: NodeJS.ProcessEnv = process.env,
): Omit<AsyncMatchConfig, 'abortRegistry' | 'fetchImpl' | 'startCursorRun' | 'awaitCursorRun'> {
  const handshakeSeconds = Number(env.BRISKLY_SYNC_ORCHESTRATOR_HANDSHAKE_TIMEOUT ?? 60);
  const llmSeconds = Number(env.BRISKLY_SYNC_LLM_TIMEOUT ?? 900);

  return {
    callbackUrl: (env.BRISKLY_SYNC_CALLBACK_URL ?? '').trim(),
    captureSecret: (env.BRISKLY_SYNC_CAPTURE_SECRET ?? '').trim(),
    handshakeTimeoutMs: Math.max(1, handshakeSeconds) * 1000,
    llmTimeoutMs: Math.max(1, llmSeconds) * 1000,
  };
}
