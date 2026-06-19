/** Конфигурация рантайма из переменных окружения. */
export interface AppConfig {
  /** Порт HTTP-сервера (PORT, по умолчанию 3000). */
  port: number;
  /** Минимальное смещение курсора при «дрожании» (MOUSE_JIGGLE_MIN_PX). */
  mouseJiggleMinPx: number;
  /** Максимальное смещение курсора при «дрожании» (MOUSE_JIGGLE_MAX_PX). */
  mouseJiggleMaxPx: number;
  /** Сколько карточек организаций целимся увидеть при resolve (RESOLVE_CANDIDATE_LIMIT). */
  resolveCandidateLimit: number;
  /** Сколько итераций скролла без новых отзывов до остановки (SYNC_MAX_IDLE_ITERATIONS). */
  syncMaxIdleIterations: number;
  /** Пауза между скроллами при sync-reviews (SYNC_SCROLL_DELAY_MS). */
  syncScrollDelayMs: number;
  /** Таймаут навигации Playwright (NAVIGATION_TIMEOUT_MS). */
  navigationTimeoutMs: number;
  /** Запуск Chromium без UI (HEADLESS). */
  headless: boolean;
}

/** Прочитать целое из env; при невалидном значении — fallback. */
function parseIntEnv(name: string, fallback: number): number {
  const raw = process.env[name];
  if (raw === undefined || raw === '') {
    return fallback;
  }

  const value = Number.parseInt(raw, 10);
  return Number.isFinite(value) ? value : fallback;
}

/** Прочитать булево из env (1/true/yes/on). */
function parseBoolEnv(name: string, fallback: boolean): boolean {
  const raw = process.env[name];
  if (raw === undefined || raw === '') {
    return fallback;
  }

  return ['1', 'true', 'yes', 'on'].includes(raw.toLowerCase());
}

/** Загрузить конфигурацию и нормализовать связанные лимиты (min ≤ max для jiggle). */
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
