import type { ModelSelection } from '@cursor/sdk';

/**
 * Auto без Team Router: id `default` из Cursor.models.list().
 * `auto-smart` — Cursor Router (Teams/Enterprise), на многих ключах недоступен.
 */
export const CURSOR_DEFAULT_AUTO_MODEL_ID = 'default';

/** Cursor Router (Teams/Enterprise). */
export const CURSOR_ROUTER_MODEL_ID = 'auto-smart';

/** @deprecated Используйте CURSOR_ROUTER_MODEL_ID. */
export const CURSOR_AUTO_MODEL_ID = CURSOR_ROUTER_MODEL_ID;

/** Дефолт sidecar: Auto (`default`), доступен без Router. */
export const DEFAULT_CURSOR_MODEL_ID = CURSOR_DEFAULT_AUTO_MODEL_ID;

export type CursorOptimizeFor = 'cost' | 'balanced' | 'intelligence';

export const DEFAULT_CURSOR_OPTIMIZE_FOR: CursorOptimizeFor = 'balanced';

const OPTIMIZE_FOR_VALUES = new Set<string>(['cost', 'balanced', 'intelligence']);

export interface ResolveCursorModelInput {
  /** Явный id (HTTP/CLI); иначе CURSOR_MODEL / default. */
  modelId?: string;
  /** Явный optimize_for; иначе CURSOR_OPTIMIZE_FOR / default для auto-smart. */
  optimizeFor?: string;
  env?: NodeJS.ProcessEnv;
}

/**
 * Собирает ModelSelection для Agent.create.
 * `auto` → `default`; `auto-smart` остаётся Router (если ключ его даёт).
 */
export function resolveCursorModel(input: ResolveCursorModelInput = {}): ModelSelection {
  const env = input.env ?? process.env;
  const rawId = (input.modelId ?? env.CURSOR_MODEL ?? DEFAULT_CURSOR_MODEL_ID).trim();
  const id = normalizeCursorModelId(rawId);

  if (!isCursorRouterModel(id)) {
    return { id };
  }

  const optimizeFor = resolveOptimizeFor(input.optimizeFor ?? env.CURSOR_OPTIMIZE_FOR);
  return {
    id,
    params: [{ id: 'optimize_for', value: optimizeFor }],
  };
}

export function normalizeCursorModelId(raw: string): string {
  const trimmed = raw.trim();
  if (trimmed === '') {
    return DEFAULT_CURSOR_MODEL_ID;
  }
  const lower = trimmed.toLowerCase();
  if (lower === 'auto') {
    return CURSOR_DEFAULT_AUTO_MODEL_ID;
  }
  if (lower === 'auto-smart') {
    return CURSOR_ROUTER_MODEL_ID;
  }
  return trimmed;
}

/** Router model (`auto-smart`) — для optimize_for. */
export function isCursorRouterModel(modelId: string): boolean {
  return normalizeCursorModelId(modelId) === CURSOR_ROUTER_MODEL_ID;
}

/** @deprecated Используйте isCursorRouterModel. */
export function isCursorAutoModel(modelId: string): boolean {
  return isCursorRouterModel(modelId);
}

function resolveOptimizeFor(raw: string | undefined): CursorOptimizeFor {
  const value = (raw ?? DEFAULT_CURSOR_OPTIMIZE_FOR).trim().toLowerCase();
  if (OPTIMIZE_FOR_VALUES.has(value)) {
    return value as CursorOptimizeFor;
  }
  return DEFAULT_CURSOR_OPTIMIZE_FOR;
}
