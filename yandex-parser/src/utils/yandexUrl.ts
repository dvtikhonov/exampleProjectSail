/**
 * Парсинг и нормализация URL Яндекс.Карт, рейтингов и дат.
 */
/** Допустимые хосты карт. */
const YANDEX_MAPS_HOST_PATTERN = /^yandex\.(ru|com|kz|com\.tr)$/i;
/** Прямая карточка: /maps/org/{slug}/{id}. */
const ORG_URL_PATTERN = /\/maps\/org\/[^/]+\/(\d+)\/?/i;
/** Ссылка внутри выдачи: /org/{slug}/{id}. */
const ORG_LINK_PATTERN = /\/org\/[^/]+\/(\d+)/i;

/** Проверить, что URL относится к доменам Яндекс.Карт. */
export function isYandexMapsUrl(url: string): boolean {
  try {
    const parsed = new URL(url);
    return YANDEX_MAPS_HOST_PATTERN.test(parsed.hostname) && parsed.pathname.includes('/maps');
  } catch {
    return false;
  }
}

/** org_id из URL вида /maps/org/{slug}/{id}. */
export function extractOrgIdFromUrl(url: string): string | null {
  const match = url.match(ORG_URL_PATTERN);
  return match?.[1] ?? null;
}

/** Канонический URL: https://{host}/maps/org/{slug}/{id}/. */
export function normalizeOrgUrl(url: string, orgId: string, slug = 'organization'): string {
  try {
    const parsed = new URL(url);
    const match = parsed.pathname.match(/\/maps\/org\/([^/]+)\/\d+/i);
    const resolvedSlug = match?.[1] ?? slug;
    return `${parsed.origin}/maps/org/${resolvedSlug}/${orgId}/`;
  } catch {
    return `https://yandex.ru/maps/org/${slug}/${orgId}/`;
  }
}

/** URL вкладки отзывов: canonical_url + /reviews/. */
export function buildReviewsUrl(canonicalUrl: string): string {
  const trimmed = canonicalUrl.replace(/\/+$/, '');
  return `${trimmed}/reviews/`;
}

/** Прямая ссылка на карточку (не поисковая выдача). */
export function isDirectOrgUrl(url: string): boolean {
  return ORG_URL_PATTERN.test(url);
}

/** org_id из произвольного href внутри выдачи (/org/.../id). */
export function extractOrgIdFromHref(href: string): string | null {
  const match = href.match(ORG_LINK_PATTERN);
  return match?.[1] ?? null;
}

/** Рейтинг из числа или строки («4,8», «4.8»). */
export function parseRating(value: unknown): number | null {
  if (typeof value === 'number' && Number.isFinite(value)) {
    return value;
  }

  if (typeof value !== 'string') {
    return null;
  }

  const normalized = value.replace(',', '.').replace(/[^\d.]/g, '');
  const parsed = Number.parseFloat(normalized);
  return Number.isFinite(parsed) ? parsed : null;
}

/** Целочисленный счётчик из числа или текста с пробелами. */
export function parseCount(value: unknown): number | null {
  if (typeof value === 'number' && Number.isFinite(value)) {
    return Math.trunc(value);
  }

  if (typeof value !== 'string') {
    return null;
  }

  const digits = value.replace(/\s/g, '').match(/\d+/);
  if (!digits) {
    return null;
  }

  return Number.parseInt(digits[0], 10);
}

/**
 * Число отзывов из текста карточки Яндекса.
 * Поддерживает «24 отзыва» и вкладки «Отзывы24» без ложного совпадения «Фото11Отзывы24» → 11.
 */
export function parseReviewsCountFromText(text: string): number | null {
  const classicMatch = text.match(
    /(?:^|[^\d,])(\d{1,3}(?:\s\d{3})*|\d+)\s+отзыв(?:ов|а|ы)?(?:[^\p{L}]|$)/iu,
  );

  if (classicMatch) {
    return parseCount(classicMatch[1]);
  }

  const tabMatch = text.match(/отзыв(?:ов|а|ы)?\s*(\d{1,3}(?:\s\d{3})*|\d+)/iu);

  if (tabMatch) {
    return parseCount(tabMatch[1]);
  }

  return null;
}

/** Дата публикации: ISO-строка или unix (сек/мс) → ISO UTC. */
export function parsePublishedAt(value: unknown): string | null {
  if (typeof value === 'number' && Number.isFinite(value)) {
    // Unix-секунды vs миллисекунды: порог ~2001 год в мс.
    const millis = value > 1_000_000_000_000 ? value : value * 1000;
    return new Date(millis).toISOString();
  }

  if (typeof value === 'string' && value.trim() !== '') {
    const date = new Date(value);
    if (!Number.isNaN(date.getTime())) {
      return date.toISOString();
    }
  }

  return null;
}
