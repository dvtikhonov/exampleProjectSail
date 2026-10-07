import type { MatchLineResult } from '../orchestrator/types.js';

export interface MatchCompleteSuccessPayload {
  session_id: string;
  match_generation: string;
  match_lines: MatchLineResult[];
  raw_text?: string | null;
  error?: undefined;
}

export interface MatchCompleteErrorPayload {
  session_id: string;
  match_generation: string;
  error: string;
  match_lines?: undefined;
  raw_text?: string | null;
}

export type MatchCompletePayload = MatchCompleteSuccessPayload | MatchCompleteErrorPayload;

export interface PostMatchCompleteOptions {
  callbackUrl: string;
  captureSecret: string;
  payload: MatchCompletePayload;
  fetchImpl?: typeof fetch;
  /** HTTP timeout колбэка, мс (default 30_000). */
  timeoutMs?: number;
}

export interface PostMatchCompleteResult {
  ok: boolean;
  status: number;
  body: string;
}

/**
 * POST колбэка в PHP: /api/food/internal/briskly-sync/match-complete.
 * Заголовок X-Briskly-Capture-Secret.
 */
export async function postMatchComplete(
  options: PostMatchCompleteOptions,
): Promise<PostMatchCompleteResult> {
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
        'X-Briskly-Capture-Secret': options.captureSecret,
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
