/** Runtime configuration from environment variables. */
export interface AppConfig {
  port: number;
  mouseJiggleMinPx: number;
  mouseJiggleMaxPx: number;
  resolveCandidateLimit: number;
  syncMaxIdleIterations: number;
  syncScrollDelayMs: number;
  navigationTimeoutMs: number;
  headless: boolean;
}

function parseIntEnv(name: string, fallback: number): number {
  const raw = process.env[name];
  if (raw === undefined || raw === '') {
    return fallback;
  }

  const value = Number.parseInt(raw, 10);
  return Number.isFinite(value) ? value : fallback;
}

function parseBoolEnv(name: string, fallback: boolean): boolean {
  const raw = process.env[name];
  if (raw === undefined || raw === '') {
    return fallback;
  }

  return ['1', 'true', 'yes', 'on'].includes(raw.toLowerCase());
}

/** Load application configuration from environment. */
export function loadConfig(): AppConfig {
  const mouseJiggleMinPx = parseIntEnv('MOUSE_JIGGLE_MIN_PX', 10);
  const mouseJiggleMaxPx = parseIntEnv('MOUSE_JIGGLE_MAX_PX', 80);

  return {
    port: parseIntEnv('PORT', 3000),
    mouseJiggleMinPx: Math.max(10, mouseJiggleMinPx),
    mouseJiggleMaxPx: Math.max(mouseJiggleMinPx, mouseJiggleMaxPx),
    resolveCandidateLimit: parseIntEnv('RESOLVE_CANDIDATE_LIMIT', 30),
    syncMaxIdleIterations: parseIntEnv('SYNC_MAX_IDLE_ITERATIONS', 15),
    syncScrollDelayMs: parseIntEnv('SYNC_SCROLL_DELAY_MS', 800),
    navigationTimeoutMs: parseIntEnv('NAVIGATION_TIMEOUT_MS', 60_000),
    headless: parseBoolEnv('HEADLESS', true),
  };
}

export const config = loadConfig();
