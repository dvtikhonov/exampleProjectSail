/**
 * POST колбэка OCR → PHP /api/food/internal/schedule-photo/ocr-complete.
 */

import type { ScheduleOcrDiscarded, ScheduleOcrEntry } from './parseScheduleOcrJson.js';

export interface ScheduleOcrCompleteSuccessPayload {
  run_id: string;
  entries: ScheduleOcrEntry[];
  discarded?: ScheduleOcrDiscarded[];
  error?: undefined;
}

export interface ScheduleOcrCompleteErrorPayload {
  run_id: string;
  error: string;
  entries?: undefined;
  discarded?: ScheduleOcrDiscarded[];
}

export type ScheduleOcrCompletePayload =
  | ScheduleOcrCompleteSuccessPayload
  | ScheduleOcrCompleteErrorPayload;

export interface PostScheduleOcrCompleteOptions {
  callbackUrl: string;
  captureSecret: string;
  payload: ScheduleOcrCompletePayload;
  fetchImpl?: typeof fetch;
  timeoutMs?: number;
}

export interface PostScheduleOcrCompleteResult {
  ok: boolean;
  status: number;
  body: string;
}

/**
 * Колбэк с заголовком X-Schedule-Photo-Capture-Secret.
 */
export async function postScheduleOcrComplete(
  options: PostScheduleOcrCompleteOptions,
): Promise<PostScheduleOcrCompleteResult> {
  const fetchImpl = options.fetchImpl ?? fetch;
  const timeoutMs = options.timeoutMs ?? 30_000;
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);

  try {
    const response = await fetchImpl(options.callbackUrl, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Schedule-Photo-Capture-Secret': options.captureSecret,
      },
      body: JSON.stringify(options.payload),
      signal: controller.signal,
    });
    const body = await response.text();
    return { ok: response.ok, status: response.status, body };
  } finally {
    clearTimeout(timer);
  }
}
