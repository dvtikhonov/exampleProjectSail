/**
 * Отбор Bearer для CDP capture: длина как в service-c + форма JWT (3 сегмента).
 * Короткий/мусорный Authorization не принимаем — ждём следующий request.
 */
export function isAcceptableBrisklyBearerToken(token: string): boolean {
  if (token.length < 10 || token.length > 4096) {
    return false;
  }

  const parts = token.split('.');
  if (parts.length !== 3) {
    return false;
  }

  return parts.every((part) => part.length > 0);
}
