export interface BrisklyClientConfig {
  /** Bearer JWT (без префикса Bearer). */
  token: string;
  /** База без завершающего слэша. */
  baseUrl?: string;
  /** Задержка между постраничными запросами snapshot, мс. */
  delayBetweenRequestsMs?: number;
  /** Лимит позиций на страницу get-list. */
  defaultPageLimit?: number;
  /** Максимум страниц при snapshot. */
  maxSnapshotPages?: number;
  /** Переопределение fetch (для тестов). */
  fetchImpl?: typeof fetch;
}

export const DEFAULT_BRISKLY_BASE_URL = 'https://briskly.business/api/company';

export function resolveClientConfig(
  partial: BrisklyClientConfig,
): Required<Omit<BrisklyClientConfig, 'fetchImpl'>> & { fetchImpl: typeof fetch } {
  const token = partial.token?.trim();
  if (!token) {
    throw new Error('Briskly token is required (BRISKLY_TOKEN / config.token)');
  }

  return {
    token,
    baseUrl: (partial.baseUrl ?? DEFAULT_BRISKLY_BASE_URL).replace(/\/$/, ''),
    delayBetweenRequestsMs: partial.delayBetweenRequestsMs ?? 250,
    defaultPageLimit: partial.defaultPageLimit ?? 50,
    maxSnapshotPages: partial.maxSnapshotPages ?? 200,
    fetchImpl: partial.fetchImpl ?? fetch,
  };
}
