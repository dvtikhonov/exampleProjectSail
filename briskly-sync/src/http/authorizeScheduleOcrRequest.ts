/**
 * Входная проверка POST /schedule-ocr до чтения тела.
 * Секрет: SCHEDULE_PHOTO_CAPTURE_SECRET, иначе BRISKLY_SYNC_CAPTURE_SECRET.
 * Заголовок: X-Schedule-Photo-Capture-Secret, иначе X-Briskly-Capture-Secret.
 */

import { createHash, timingSafeEqual } from 'node:crypto';
import type { IncomingHttpHeaders } from 'node:http';

export const SCHEDULE_PHOTO_CAPTURE_SECRET_HEADER = 'x-schedule-photo-capture-secret';
export const BRISKLY_CAPTURE_SECRET_HEADER = 'x-briskly-capture-secret';

export interface ScheduleOcrUnauthorized {
  ok: false;
  status: 401;
  body: { error: 'unauthorized' };
}

export type ScheduleOcrAuthResult = { ok: true } | ScheduleOcrUnauthorized;

const UNAUTHORIZED: ScheduleOcrUnauthorized = {
  ok: false,
  status: 401,
  body: { error: 'unauthorized' },
};

/**
 * Непустой секрет из env: сначала SchedulePhoto, затем общий capture-секрет.
 */
export function resolveScheduleOcrInboundSecret(env: NodeJS.ProcessEnv): string {
  const preferred = (env.SCHEDULE_PHOTO_CAPTURE_SECRET ?? '').trim();
  if (preferred !== '') {
    return preferred;
  }

  return (env.BRISKLY_SYNC_CAPTURE_SECRET ?? '').trim();
}

/**
 * Пустой секрет или несовпадение заголовка — 401, без чтения тела.
 */
export function authorizeScheduleOcrRequest(
  headers: IncomingHttpHeaders,
  env: NodeJS.ProcessEnv = process.env,
): ScheduleOcrAuthResult {
  const expected = resolveScheduleOcrInboundSecret(env);
  const provided = readScheduleOcrSecretHeader(headers);
  if (expected === '' || provided === undefined || !sha256SecretsEqual(expected, provided)) {
    return UNAUTHORIZED;
  }

  return { ok: true };
}

/**
 * Сначала свой заголовок SchedulePhoto; пустой или отсутствующий — общий capture-заголовок.
 */
function readScheduleOcrSecretHeader(headers: IncomingHttpHeaders): string | undefined {
  const preferred = firstHeaderValue(headers, SCHEDULE_PHOTO_CAPTURE_SECRET_HEADER);
  if (preferred !== undefined && preferred !== '') {
    return preferred;
  }

  return firstHeaderValue(headers, BRISKLY_CAPTURE_SECRET_HEADER);
}

function firstHeaderValue(headers: IncomingHttpHeaders, name: string): string | undefined {
  const raw = headers[name];
  if (Array.isArray(raw)) {
    return raw[0];
  }

  return raw;
}

/**
 * SHA-256 обеих сторон, затем timingSafeEqual: длина секрета не влияет на сравнение.
 */
function sha256SecretsEqual(expected: string, provided: string): boolean {
  const expectedDigest = createHash('sha256').update(expected, 'utf8').digest();
  const providedDigest = createHash('sha256').update(provided, 'utf8').digest();

  return timingSafeEqual(expectedDigest, providedDigest);
}
