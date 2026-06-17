const YANDEX_MAPS_HOST_PATTERN = /^yandex\.(ru|com|kz|com\.tr)$/i;
const ORG_URL_PATTERN = /\/maps\/org\/[^/]+\/(\d+)\/?/i;
const ORG_LINK_PATTERN = /\/org\/[^/]+\/(\d+)/i;

/** Validate that URL belongs to Yandex Maps domains. */
export function isYandexMapsUrl(url: string): boolean {
  try {
    const parsed = new URL(url);
    return YANDEX_MAPS_HOST_PATTERN.test(parsed.hostname) && parsed.pathname.includes('/maps');
  } catch {
    return false;
  }
}

/** Extract organization id from a Yandex Maps org URL. */
export function extractOrgIdFromUrl(url: string): string | null {
  const match = url.match(ORG_URL_PATTERN);
  return match?.[1] ?? null;
}

/** Normalize org URL to https://yandex.ru/maps/org/{slug}/{id}/ form. */
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

/** Build reviews page URL from canonical org URL. */
export function buildReviewsUrl(canonicalUrl: string): string {
  const trimmed = canonicalUrl.replace(/\/+$/, '');
  return `${trimmed}/reviews/`;
}

/** Detect whether URL points directly to an organization card. */
export function isDirectOrgUrl(url: string): boolean {
  return ORG_URL_PATTERN.test(url);
}

/** Extract org id from arbitrary href inside search results. */
export function extractOrgIdFromHref(href: string): string | null {
  const match = href.match(ORG_LINK_PATTERN);
  return match?.[1] ?? null;
}

/** Parse numeric rating from text like "4,8" or "4.8". */
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

/** Parse integer counter from mixed text/number values. */
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

/** Parse ISO or unix timestamp to ISO string. */
export function parsePublishedAt(value: unknown): string | null {
  if (typeof value === 'number' && Number.isFinite(value)) {
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
