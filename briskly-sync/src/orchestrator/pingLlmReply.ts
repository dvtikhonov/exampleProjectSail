/** Промпт smoke-проверки Cursor SDK / LLM. */
export const LLM_PING_PROMPT =
  'Reply with exactly one word and nothing else: PONG';

/** Ожидаемый ответ (после normalize). */
export const LLM_PING_EXPECTED = 'PONG';

/** Default timeout live ping (create+send+wait), мс. */
export const LLM_PING_TIMEOUT_MS = 60_000;

/**
 * Нормализует ответ ping: trim + снимает обёртку кавычками.
 */
export function normalizeLlmPingReply(text: string): string {
  return text
    .trim()
    .replace(/^["'`]+|["'`]+$/g, '')
    .trim();
}

/**
 * True, если ответ — ровно PONG (без учёта регистра).
 */
export function isLlmPingOk(text: string): boolean {
  return normalizeLlmPingReply(text).toUpperCase() === LLM_PING_EXPECTED;
}
