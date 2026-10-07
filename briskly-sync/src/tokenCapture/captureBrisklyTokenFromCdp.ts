import { chromium, type Browser, type Page, type Request } from 'playwright-core';
import { isAcceptableBrisklyBearerToken } from './isAcceptableBrisklyBearerToken.js';
import { normalizeBearerToken } from './normalizeBearerToken.js';

/** Коды ошибок CDP-захвата (без секретов в message). */
export type CaptureTokenErrorCode =
  | 'cdp_unavailable'
  | 'no_briskly_tab'
  | 'not_logged_in'
  | 'no_token_observed'
  | 'timeout';

export class BrisklyTokenCaptureError extends Error {
  readonly code: CaptureTokenErrorCode;

  constructor(code: CaptureTokenErrorCode, message?: string) {
    super(message ?? code);
    this.name = 'BrisklyTokenCaptureError';
    this.code = code;
  }
}

export interface CaptureBrisklyTokenResult {
  token: string;
  source: 'cdp';
}

export interface CaptureBrisklyTokenLogMeta {
  captured?: boolean;
  token_len?: number;
  error?: CaptureTokenErrorCode;
  [key: string]: unknown;
}

export interface CaptureBrisklyTokenFromCdpOptions {
  /** HTTP endpoint Chrome DevTools, default BRISKLY_CDP_URL или http://127.0.0.1:9222 */
  cdpUrl?: string;
  /** Таймаут ожидания Authorization, мс (default BRISKLY_CDP_CAPTURE_TIMEOUT_MS или 20000). */
  timeoutMs?: number;
  /** Таймаут TCP/WebSocket connect к CDP, мс (default BRISKLY_CDP_CONNECT_TIMEOUT_MS или 5000). */
  connectTimeoutMs?: number;
  /** Пауза перед reload при отсутствии трафика к /api/company/, мс. */
  idleBeforeReloadMs?: number;
  /** DI: подключение к CDP (для тестов). */
  connect?: (endpoint: string) => Promise<Browser>;
  /**
   * Лог без JWT: только meta вроде token_len / captured.
   * По умолчанию — тихий (no-op).
   */
  log?: (event: string, meta?: CaptureBrisklyTokenLogMeta) => void;
}

const DEFAULT_CDP_URL = 'http://127.0.0.1:9222';
const DEFAULT_TIMEOUT_MS = 20_000;
const DEFAULT_CONNECT_TIMEOUT_MS = 5_000;
const DEFAULT_IDLE_BEFORE_RELOAD_MS = 1_500;
const BRISKLY_HOST_MARKER = 'briskly.business';
const API_PATH_MARKER = '/api/company/';

/**
 * Захват Bearer JWT из уже запущенного Chrome через CDP (playwright-core).
 * JWT в лог не пишет — только token_len / captured.
 */
export async function captureBrisklyTokenFromCdp(
  options: CaptureBrisklyTokenFromCdpOptions = {},
): Promise<CaptureBrisklyTokenResult> {
  const cdpUrl = (options.cdpUrl ?? process.env.BRISKLY_CDP_URL ?? DEFAULT_CDP_URL).trim();
  const timeoutMs = resolvePositiveInt(
    options.timeoutMs ?? process.env.BRISKLY_CDP_CAPTURE_TIMEOUT_MS,
    DEFAULT_TIMEOUT_MS,
  );
  const connectTimeoutMs = resolvePositiveInt(
    options.connectTimeoutMs ?? process.env.BRISKLY_CDP_CONNECT_TIMEOUT_MS,
    DEFAULT_CONNECT_TIMEOUT_MS,
  );
  const idleBeforeReloadMs = resolvePositiveInt(
    options.idleBeforeReloadMs,
    DEFAULT_IDLE_BEFORE_RELOAD_MS,
  );
  const connect = options.connect ?? ((endpoint: string) => chromium.connectOverCDP(endpoint));
  const log = options.log ?? (() => {});

  let browser: Browser | undefined;
  try {
    try {
      browser = await withTimeout(
        connect(cdpUrl),
        connectTimeoutMs,
        () => new BrisklyTokenCaptureError('cdp_unavailable', `CDP connect timed out after ${connectTimeoutMs}ms`),
      );
    } catch (err) {
      const error =
        err instanceof BrisklyTokenCaptureError
          ? err
          : new BrisklyTokenCaptureError(
              'cdp_unavailable',
              err instanceof Error ? err.message : 'CDP unavailable',
            );
      log('capture_failed', {
        error: error.code,
        cdp_host: (() => {
          try {
            return new URL(cdpUrl).host;
          } catch {
            return 'invalid';
          }
        })(),
        connect_err: err instanceof Error ? err.message.slice(0, 160) : 'unknown',
      });
      throw error;
    }

    const brisklyPages = findBrisklyPages(browser);
    if (brisklyPages.length === 0) {
      const error = new BrisklyTokenCaptureError(
        'no_briskly_tab',
        'No briskly.business tab in CDP session',
      );
      log('capture_failed', { error: error.code });
      throw error;
    }

    const cabinetPages = brisklyPages.filter((page) => !isBrisklyAuthUrl(page.url()));
    if (cabinetPages.length === 0) {
      const error = new BrisklyTokenCaptureError(
        'not_logged_in',
        'Briskly tab is on /auth — login (SMS) required before capture',
      );
      log('capture_failed', {
        error: error.code,
        briskly_tabs: brisklyPages.length,
      });
      throw error;
    }

    const deadline = Date.now() + timeoutMs;
    const watcher = watchForBearerToken(browser, log);

    try {
      const idleWait = Math.min(idleBeforeReloadMs, Math.max(0, deadline - Date.now()));
      if (idleWait > 0) {
        const early = await Promise.race([
          watcher.tokenPromise.then((token) => ({ ok: true as const, token })),
          sleep(idleWait).then(() => ({ ok: false as const })),
        ]);
        if (early.ok) {
          log('capture_ok', { captured: true, token_len: early.token.length });
          return { token: early.token, source: 'cdp' };
        }
      }

      // При отсутствии трафика к /api/company/ — один reload вкладки кабинета (не /auth).
      if (!watcher.sawCompanyApi() && Date.now() < deadline) {
        await reloadOnce(cabinetPages[0]!);
      }

      const remaining = deadline - Date.now();
      if (remaining <= 0) {
        throw new BrisklyTokenCaptureError('timeout', 'CDP capture timed out');
      }

      const token = await Promise.race([
        watcher.tokenPromise,
        sleep(remaining).then(() => {
          throw new BrisklyTokenCaptureError('timeout', 'CDP capture timed out');
        }),
      ]);

      log('capture_ok', { captured: true, token_len: token.length });
      return { token, source: 'cdp' };
    } catch (err) {
      if (err instanceof BrisklyTokenCaptureError) {
        log('capture_failed', { error: err.code });
        throw err;
      }
      throw err;
    } finally {
      watcher.dispose();
    }
  } finally {
    await browser?.close().catch(() => {});
  }
}

function findBrisklyPages(browser: Browser): Page[] {
  const pages: Page[] = [];
  for (const context of browser.contexts()) {
    for (const page of context.pages()) {
      try {
        if (page.url().includes(BRISKLY_HOST_MARKER)) {
          pages.push(page);
        }
      } catch {
        // вкладка закрыта / url недоступен
      }
    }
  }
  return pages;
}

function isBrisklyAuthUrl(url: string): boolean {
  try {
    const pathname = new URL(url).pathname;
    return pathname === '/auth' || pathname.startsWith('/auth/');
  } catch {
    return url.includes('/auth');
  }
}

function watchForBearerToken(
  browser: Browser,
  log: (event: string, meta?: CaptureBrisklyTokenLogMeta) => void = () => {},
): {
  tokenPromise: Promise<string>;
  dispose: () => void;
  sawCompanyApi: () => boolean;
} {
  let companyApiSeen = false;
  let settled = false;
  let resolveToken!: (token: string) => void;

  const tokenPromise = new Promise<string>((resolve) => {
    resolveToken = resolve;
  });

  const onRequest = (request: Request): void => {
    if (settled) {
      return;
    }

    const url = request.url();
    if (!url.includes(API_PATH_MARKER)) {
      return;
    }

    companyApiSeen = true;

    const headers = request.headers();
    const rawAuth = headers['authorization'] ?? headers['Authorization'];
    if (rawAuth === undefined || rawAuth === '') {
      return;
    }

    const token = normalizeBearerToken(rawAuth);
    if (token === '') {
      return;
    }

    if (!isAcceptableBrisklyBearerToken(token)) {
      // Короткий/не-JWT Authorization (часто на /auth) — пропускаем, ждём JWT.
      log('capture_skip_token', {
        token_len: token.length,
        error: 'no_token_observed',
      });
      return;
    }

    settled = true;
    resolveToken(token);
  };

  const contexts = browser.contexts();
  for (const context of contexts) {
    context.on('request', onRequest);
  }

  return {
    tokenPromise,
    dispose: () => {
      for (const context of contexts) {
        context.off('request', onRequest);
      }
    },
    sawCompanyApi: () => companyApiSeen,
  };
}

async function reloadOnce(page: Page): Promise<void> {
  try {
    await page.reload({ waitUntil: 'domcontentloaded', timeout: 15_000 });
  } catch {
    // reload может упасть на закрытой вкладке — дальше ждём таймаут/трафик
  }
}

function resolvePositiveInt(value: unknown, fallback: number): number {
  if (value === undefined || value === null || value === '') {
    return fallback;
  }
  const n = typeof value === 'number' ? value : Number(value);
  if (!Number.isFinite(n) || n <= 0) {
    return fallback;
  }
  return Math.floor(n);
}

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => {
    setTimeout(resolve, ms);
  });
}

function withTimeout<T>(
  promise: Promise<T>,
  timeoutMs: number,
  onTimeout: () => Error,
): Promise<T> {
  let timer: ReturnType<typeof setTimeout> | undefined;
  const timeoutPromise = new Promise<never>((_, reject) => {
    timer = setTimeout(() => {
      reject(onTimeout());
    }, timeoutMs);
  });

  return Promise.race([promise, timeoutPromise]).finally(() => {
    if (timer !== undefined) {
      clearTimeout(timer);
    }
  });
}
