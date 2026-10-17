/**
 * POST /schedule-ocr: handshake vision create+send → 202; wait в фоне → callback PHP.
 */

import { withTimeout } from '../orchestrator/withTimeout.js';
import {
  buildScheduleOcrPrompt,
  buildWeekdayDates,
  type ScheduleOcrCatalogDish,
} from './buildScheduleOcrPrompt.js';
import {
  parseScheduleOcrJson,
  type ScheduleOcrDiscarded,
  type ScheduleOcrEntry,
} from './parseScheduleOcrJson.js';
import {
  postScheduleOcrComplete,
  type PostScheduleOcrCompleteResult,
  type ScheduleOcrCompletePayload,
} from './postScheduleOcrComplete.js';
import { readSchedulePhotoByKey } from './readSchedulePhotoByKey.js';
import {
  awaitCursorRun,
  disposeCursorAgent,
  startScheduleOcrRun,
  type StartedScheduleOcrRun,
} from './runScheduleOcr.js';

const UUID_RE =
  /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

export interface AsyncScheduleOcrRequestBody {
  run_id?: unknown;
  /** Относительный ключ внутри SCHEDULE_PHOTO_PHOTO_ROOT. */
  photo_key?: unknown;
  date_from?: unknown;
  date_to?: unknown;
  restaurant_id?: unknown;
  category_ids?: unknown;
  catalog_scope?: unknown;
  aliases_markdown?: unknown;
  /** Offline fixture: готовый ответ LLM. */
  llm_response?: unknown;
}

export interface AsyncScheduleOcrConfig {
  callbackUrl: string;
  captureSecret: string;
  /** Каталог SCHEDULE_PHOTO_PHOTO_ROOT. Пустая строка — 503, файл не читается. */
  photoRoot: string;
  handshakeTimeoutMs: number;
  llmTimeoutMs: number;
  fetchImpl?: typeof fetch;
  startScheduleOcrRun?: typeof startScheduleOcrRun;
  awaitCursorRun?: typeof awaitCursorRun;
  disposeCursorAgent?: typeof disposeCursorAgent;
  scheduleBackground?: (task: () => Promise<void>) => void;
  readAliasesFile?: () => Promise<string>;
  log?: (message: string, meta?: Record<string, unknown>) => void;
}

export type AsyncScheduleOcrHandshakeResult =
  | { ok: true; status: 202; body: { accepted: true; phase: 'running' } }
  | { ok: false; status: 400 | 503; body: { error: string; message?: string } };

/**
 * Validate → create+send (или fixture) → 202; фон wait + callback.
 */
export async function handleAsyncScheduleOcr(
  rawBody: AsyncScheduleOcrRequestBody,
  config: AsyncScheduleOcrConfig,
): Promise<AsyncScheduleOcrHandshakeResult> {
  const validated = await validateBody(rawBody, config);
  if (!validated.ok) {
    return {
      ok: false,
      status: validated.status,
      body: { error: validated.error, message: validated.message },
    };
  }

  const value = validated.value;
  const captureSecret = config.captureSecret.trim();
  const callbackUrl = config.callbackUrl.trim();

  if (!callbackUrl) {
    return {
      ok: false,
      status: 503,
      body: {
        error: 'callback_unconfigured',
        message: 'SCHEDULE_PHOTO_CALLBACK_URL is required',
      },
    };
  }
  if (!captureSecret) {
    return {
      ok: false,
      status: 503,
      body: {
        error: 'callback_unconfigured',
        message: 'SCHEDULE_PHOTO_CAPTURE_SECRET is required for OCR callback',
      },
    };
  }

  const effectiveConfig: AsyncScheduleOcrConfig = {
    ...config,
    captureSecret,
    callbackUrl,
  };

  try {
    if (value.llmResponse !== undefined) {
      scheduleBackground(effectiveConfig, () =>
        runFixtureBackground({
          runId: value.runId,
          llmResponse: value.llmResponse!,
          config: effectiveConfig,
        }),
      );
      return { ok: true, status: 202, body: { accepted: true, phase: 'running' } };
    }

    const startFn = config.startScheduleOcrRun ?? startScheduleOcrRun;
    const disposeFn = config.disposeCursorAgent ?? disposeCursorAgent;
    const promptText = buildScheduleOcrPrompt({
      dateFrom: value.dateFrom,
      dateTo: value.dateTo,
      restaurantId: value.restaurantId,
      categoryIds: value.categoryIds,
      catalogScope: value.catalogScope,
      aliasesMarkdown: value.aliasesMarkdown,
      weekdayDates: value.weekdayDates,
    });

    const startPromise = startFn({
      promptText,
      image: { data: value.photoBase64, mimeType: value.photoMimeType },
    });

    let started: StartedScheduleOcrRun;
    try {
      started = await withTimeout(startPromise, config.handshakeTimeoutMs, 'schedule-ocr handshake');
    } catch (err) {
      void startPromise
        .then((late) => disposeFn(late.agent))
        .catch(() => undefined);
      throw err;
    }

    scheduleBackground(effectiveConfig, () =>
      runCursorBackground({
        runId: value.runId,
        started,
        config: effectiveConfig,
      }),
    );

    return { ok: true, status: 202, body: { accepted: true, phase: 'running' } };
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    config.log?.('schedule_ocr_handshake_failed', { runId: value.runId, message });
    return {
      ok: false,
      status: 503,
      body: { error: 'handshake_failed', message },
    };
  }
}

export function resolveAsyncScheduleOcrConfigFromEnv(
  env: NodeJS.ProcessEnv = process.env,
): Omit<
  AsyncScheduleOcrConfig,
  'fetchImpl' | 'startScheduleOcrRun' | 'awaitCursorRun' | 'disposeCursorAgent' | 'scheduleBackground'
> {
  const handshakeSeconds = Number(
    env.SCHEDULE_PHOTO_ORCHESTRATOR_HANDSHAKE_TIMEOUT
      ?? env.BRISKLY_SYNC_ORCHESTRATOR_HANDSHAKE_TIMEOUT
      ?? 60,
  );
  const llmSeconds = Number(env.SCHEDULE_PHOTO_LLM_TIMEOUT ?? env.BRISKLY_SYNC_LLM_TIMEOUT ?? 900);

  const callbackUrl = (
    env.SCHEDULE_PHOTO_CALLBACK_URL
    ?? ''
  ).trim()
    || defaultCallbackUrlFromBriskly(env.BRISKLY_SYNC_CALLBACK_URL ?? '');

  const captureSecret = (
    env.SCHEDULE_PHOTO_CAPTURE_SECRET
    ?? env.BRISKLY_SYNC_CAPTURE_SECRET
    ?? ''
  ).trim();

  const photoRoot = (env.SCHEDULE_PHOTO_PHOTO_ROOT ?? '').trim();

  return {
    callbackUrl,
    captureSecret,
    photoRoot,
    handshakeTimeoutMs: Math.max(1, handshakeSeconds) * 1000,
    llmTimeoutMs: Math.max(1, llmSeconds) * 1000,
  };
}

function defaultCallbackUrlFromBriskly(brisklyUrl: string): string {
  const trimmed = brisklyUrl.trim();
  if (!trimmed) {
    return '';
  }
  return trimmed.replace(
    /\/api\/food\/internal\/briskly-sync\/match-complete\/?$/,
    '/api/food/internal/schedule-photo/ocr-complete',
  );
}

function scheduleBackground(
  config: AsyncScheduleOcrConfig,
  task: () => Promise<void>,
): void {
  const schedule =
    config.scheduleBackground
    ?? ((fn) => {
      void fn().catch((err) => {
        config.log?.('schedule_ocr_background_unhandled', {
          message: err instanceof Error ? err.message : String(err),
        });
      });
    });
  schedule(task);
}

async function runFixtureBackground(args: {
  runId: string;
  llmResponse: string;
  config: AsyncScheduleOcrConfig;
}): Promise<void> {
  const { runId, llmResponse, config } = args;
  try {
    const parsed = parseScheduleOcrJson(llmResponse);
    await sendSuccess({
      runId,
      entries: parsed.entries,
      discarded: parsed.discarded,
      config,
    });
  } catch (err) {
    await sendError({
      runId,
      error: err instanceof Error ? err.message : String(err),
      config,
    });
  }
}

async function runCursorBackground(args: {
  runId: string;
  started: StartedScheduleOcrRun;
  config: AsyncScheduleOcrConfig;
}): Promise<void> {
  const { runId, started, config } = args;
  const awaitFn = config.awaitCursorRun ?? awaitCursorRun;
  const disposeFn = config.disposeCursorAgent ?? disposeCursorAgent;

  try {
    const rawText = await withTimeout(
      awaitFn(started.run),
      config.llmTimeoutMs,
      'schedule-ocr llm wait',
    );
    const parsed = parseScheduleOcrJson(rawText);
    await sendSuccess({
      runId,
      entries: parsed.entries,
      discarded: parsed.discarded,
      config,
    });
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    await sendError({
      runId,
      error: message.slice(0, 2000),
      config,
    });
  } finally {
    await disposeFn(started.agent);
  }
}

async function sendSuccess(args: {
  runId: string;
  entries: ScheduleOcrEntry[];
  discarded: ScheduleOcrDiscarded[];
  config: AsyncScheduleOcrConfig;
}): Promise<void> {
  const payload: ScheduleOcrCompletePayload = {
    run_id: args.runId,
    entries: args.entries,
    discarded: args.discarded,
  };
  const result = await postCallback(args.config, payload);
  args.config.log?.('schedule_ocr_callback_sent', {
    runId: args.runId,
    status: result.status,
    ok: result.ok,
  });
}

async function sendError(args: {
  runId: string;
  error: string;
  config: AsyncScheduleOcrConfig;
}): Promise<void> {
  const payload: ScheduleOcrCompletePayload = {
    run_id: args.runId,
    error: args.error,
  };
  const result = await postCallback(args.config, payload);
  args.config.log?.('schedule_ocr_callback_error_sent', {
    runId: args.runId,
    status: result.status,
    ok: result.ok,
  });
}

async function postCallback(
  config: AsyncScheduleOcrConfig,
  payload: ScheduleOcrCompletePayload,
): Promise<PostScheduleOcrCompleteResult> {
  try {
    return await postScheduleOcrComplete({
      callbackUrl: config.callbackUrl,
      captureSecret: config.captureSecret,
      payload,
      fetchImpl: config.fetchImpl,
    });
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    config.log?.('schedule_ocr_callback_failed', { message });
    return { ok: false, status: 0, body: message };
  }
}

type ValidatedBody =
  | {
      ok: true;
      value: {
        runId: string;
        dateFrom: string;
        dateTo: string;
        restaurantId: number;
        categoryIds: number[];
        catalogScope: ScheduleOcrCatalogDish[];
        aliasesMarkdown: string;
        weekdayDates: Array<{ weekdayRu: string; date: string }>;
        photoBase64: string;
        photoMimeType: string;
        llmResponse?: string;
      };
    }
  | { ok: false; status: 400 | 503; error: string; message: string };

async function validateBody(
  body: AsyncScheduleOcrRequestBody,
  config: AsyncScheduleOcrConfig,
): Promise<ValidatedBody> {
  const runId = asNonEmptyString(body.run_id);
  if (!runId || !isUuid(runId)) {
    return invalidBody('run_id must be a uuid');
  }

  const dateFrom = asNonEmptyString(body.date_from);
  const dateTo = asNonEmptyString(body.date_to);
  if (!dateFrom || !dateTo) {
    return invalidBody('date_from and date_to are required (Y-m-d)');
  }

  let weekdayDates: Array<{ weekdayRu: string; date: string }>;
  try {
    weekdayDates = buildWeekdayDates(dateFrom, dateTo);
  } catch (err) {
    return invalidBody(err instanceof Error ? err.message : String(err));
  }

  const restaurantId = asPositiveInt(body.restaurant_id);
  if (restaurantId === undefined) {
    return invalidBody('restaurant_id must be a positive integer');
  }

  const categoryIds = asPositiveIntArray(body.category_ids);
  if (!categoryIds || categoryIds.length === 0) {
    return invalidBody('category_ids must be a non-empty array of ids');
  }

  const catalogScope = asCatalogScope(body.catalog_scope);
  if (catalogScope === undefined) {
    return invalidBody('catalog_scope must be a non-empty array of {id,name}');
  }

  let aliasesMarkdown =
    typeof body.aliases_markdown === 'string' ? body.aliases_markdown : '';
  if (aliasesMarkdown.trim() === '' && config.readAliasesFile) {
    try {
      aliasesMarkdown = await config.readAliasesFile();
    } catch {
      aliasesMarkdown = '';
    }
  }

  if (typeof body.photo_key !== 'string' || body.photo_key.trim() === '') {
    return invalidBody('photo_key is required');
  }

  const photo = await readSchedulePhotoByKey({
    photoRoot: config.photoRoot,
    photoKey: body.photo_key,
  });
  if (!photo.ok) {
    return {
      ok: false,
      status: photo.status,
      error: photo.error,
      message: photo.message,
    };
  }

  const llmResponse =
    typeof body.llm_response === 'string' && body.llm_response.trim() !== ''
      ? body.llm_response
      : undefined;

  return {
    ok: true,
    value: {
      runId,
      dateFrom,
      dateTo,
      restaurantId,
      categoryIds,
      catalogScope,
      aliasesMarkdown,
      weekdayDates,
      photoBase64: photo.bytes.toString('base64'),
      photoMimeType: photo.mimeType,
      llmResponse,
    },
  };
}

function invalidBody(message: string): ValidatedBody {
  return { ok: false, status: 400, error: 'invalid_body', message };
}

function asCatalogScope(value: unknown): ScheduleOcrCatalogDish[] | undefined {
  if (!Array.isArray(value) || value.length === 0) {
    return undefined;
  }
  const out: ScheduleOcrCatalogDish[] = [];
  for (const row of value) {
    if (!row || typeof row !== 'object' || Array.isArray(row)) {
      continue;
    }
    const r = row as Record<string, unknown>;
    const id = asPositiveInt(r.id);
    const name = asNonEmptyString(r.name);
    if (id === undefined || !name) {
      continue;
    }
    const dish: ScheduleOcrCatalogDish = { id, name };
    const categoryId = asPositiveInt(r.category_id);
    if (categoryId !== undefined) {
      dish.category_id = categoryId;
    }
    const categoryName = asNonEmptyString(r.category_name);
    if (categoryName) {
      dish.category_name = categoryName;
    }
    out.push(dish);
  }
  return out.length > 0 ? out : undefined;
}

function asPositiveInt(value: unknown): number | undefined {
  const n = typeof value === 'number' ? value : typeof value === 'string' ? Number(value) : NaN;
  if (!Number.isInteger(n) || n < 1) {
    return undefined;
  }
  return n;
}

function asPositiveIntArray(value: unknown): number[] | undefined {
  if (!Array.isArray(value)) {
    return undefined;
  }
  const out: number[] = [];
  for (const item of value) {
    const n = asPositiveInt(item);
    if (n === undefined) {
      return undefined;
    }
    out.push(n);
  }
  return out;
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
