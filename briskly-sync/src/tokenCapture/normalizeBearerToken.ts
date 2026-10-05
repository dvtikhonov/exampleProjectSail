/**
 * Нормализация Bearer Briskly (семантика как BrisklySyncBearerToken в service-c):
 * trim + срезание префикса «Bearer ».
 */
export function normalizeBearerToken(raw: string): string {
  let token = raw.trim();
  if (token === '') {
    return '';
  }

  if (/^Bearer\s+/i.test(token)) {
    token = token.replace(/^Bearer\s+/i, '').trim();
  }

  return token;
}
