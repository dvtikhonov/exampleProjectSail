/**
 * Отбор Bearer для CDP capture: длина как в service-c + форма JWT (3 сегмента).
 * Истёкший JWT (exp) пропускаем — ждём свежий request после reload.
 */
export function isAcceptableBrisklyBearerToken(
  token: string,
  nowSec: number = Math.floor(Date.now() / 1000),
): boolean {
  if (token.length < 10 || token.length > 4096) {
    return false;
  }

  const parts = token.split('.');
  if (parts.length !== 3 || parts.some((part) => part.length === 0)) {
    return false;
  }

  return !isJwtExpired(parts[1]!, nowSec);
}

/**
 * JWT exp из payload (без проверки подписи). Нет exp — считаем годным.
 */
export function isJwtExpired(payloadSegment: string, nowSec: number): boolean {
  try {
    const padded = payloadSegment.replace(/-/g, '+').replace(/_/g, '/');
    const padLen = (4 - (padded.length % 4)) % 4;
    const json = Buffer.from(padded + '='.repeat(padLen), 'base64').toString('utf8');
    const payload = JSON.parse(json) as { exp?: unknown };
    if (typeof payload.exp !== 'number' || !Number.isFinite(payload.exp)) {
      return false;
    }

    return payload.exp <= nowSec + 30;
  } catch {
    // Невалидный payload / нет JSON — как отсутствие exp: форму JWT уже проверили.
    return false;
  }
}
