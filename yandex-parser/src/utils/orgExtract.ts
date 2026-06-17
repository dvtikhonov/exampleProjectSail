import { pickRecord, pickString } from './jsonExtract.js';
import { extractOrgIdFromHref, extractOrgIdFromUrl, parseCount, parseRating } from './yandexUrl.js';
import type { OrganizationCandidate } from '../types.js';

const HREF_KEYS = ['uri', 'url', 'link', 'permalink', 'canonicalUrl'] as const;
const ORG_NAME_KEYS = ['shortName', 'name', 'title', 'caption', 'fullName'] as const;

/** Reject tab-navigation text accidentally scraped as an organization name. */
export function isPlausibleOrgName(name: string): boolean {
  const trimmed = name.trim();

  if (!trimmed || trimmed.length > 100) {
    return false;
  }

  if (/Обзор.*(?:Товары|Фото|Отзывы|Филиалы|Особенности)/i.test(trimmed)) {
    return false;
  }

  if (/^(Обзор|Товары и услуги|Новости\d*|Фото\d*|Отзывы\d*|Филиалы|Особенности)/i.test(trimmed) && trimmed.length > 25) {
    return false;
  }

  return true;
}

/** Extract organization id only from /org/.../{id} paths in href-like fields. */
export function pickOrgIdFromHref(record: Record<string, unknown>): string | null {
  for (const key of HREF_KEYS) {
    const value = pickString(record, [key]);

    if (!value) {
      continue;
    }

    const orgId = extractOrgIdFromHref(value) ?? extractOrgIdFromUrl(value);

    if (orgId) {
      return orgId;
    }
  }

  return null;
}

/** Check whether a JSON record belongs to the requested organization. */
export function recordMatchesOrgId(record: Record<string, unknown>, orgId: string): boolean {
  const fromHref = pickOrgIdFromHref(record);

  if (fromHref !== null) {
    return fromHref === orgId;
  }

  const direct = record.id ?? record.orgId ?? record.businessId ?? record.companyId;
  const idStr = direct == null ? null : String(direct);

  if (idStr !== orgId) {
    return false;
  }

  return (
    pickRecord(record, ['ratingData', 'rating', 'stars']) !== null ||
    pickString(record, ['address', 'fullAddress', 'formattedAddress']) !== null ||
    pickString(record, [...ORG_NAME_KEYS]) !== null
  );
}

/** Extract slug from href or record fields. */
export function pickOrgSlug(record: Record<string, unknown>, orgId: string): string {
  for (const key of HREF_KEYS) {
    const value = pickString(record, [key]);

    if (!value) {
      continue;
    }

    const match = value.match(/\/org\/([^/]+)\/\d+/i);

    if (match?.[1]) {
      return match[1];
    }
  }

  return pickString(record, ['slug', 'seoSlug']) ?? 'organization';
}

/** Extract rating counters from a Yandex Maps business record. */
export function extractRatingFields(record: Record<string, unknown>): {
  average_rating: number | null;
  reviews_count: number | null;
  ratings_count: number | null;
} {
  const ratingRecord = pickRecord(record, ['ratingData', 'rating', 'stars', 'businessRating']);

  return {
    average_rating: parseRating(
      record.rating ??
        record.score ??
        record.averageRating ??
        ratingRecord?.score ??
        ratingRecord?.rating ??
        ratingRecord?.value ??
        ratingRecord?.avgRating,
    ),
    reviews_count: parseCount(
      record.reviewsCount ??
        record.reviews ??
        record.reviewCount ??
        ratingRecord?.reviewsCount ??
        ratingRecord?.reviews ??
        ratingRecord?.reviewCount,
    ),
    ratings_count: parseCount(
      record.ratingsCount ??
        record.ratings ??
        record.ratingCount ??
        ratingRecord?.ratingsCount ??
        ratingRecord?.ratings ??
        ratingRecord?.ratingCount,
    ),
  };
}

/** Prefer API/network fields over DOM fallbacks. */
export function mergeOrganizationCandidate(
  primary: OrganizationCandidate | null | undefined,
  fallback: OrganizationCandidate | null | undefined,
  orgId?: string,
): OrganizationCandidate | null {
  if (!primary && !fallback) {
    return null;
  }

  if (!primary) {
    return fallback ?? null;
  }

  if (!fallback) {
    return orgId ? { ...primary, org_id: orgId } : primary;
  }

  const resolvedOrgId = orgId ?? primary.org_id ?? fallback.org_id;

  return {
    org_id: resolvedOrgId,
    canonical_url: primary.canonical_url || fallback.canonical_url,
    name: pickMergedName(primary.name, fallback.name),
    address: primary.address || fallback.address,
    average_rating: primary.average_rating ?? fallback.average_rating,
    reviews_count: primary.reviews_count ?? fallback.reviews_count,
    ratings_count: primary.ratings_count ?? fallback.ratings_count,
  };
}

function pickMergedName(primaryName: string, fallbackName: string): string {
  if (isPlausibleOrgName(primaryName)) {
    return primaryName;
  }

  if (isPlausibleOrgName(fallbackName)) {
    return fallbackName;
  }

  return primaryName || fallbackName;
}

/** Build a candidate from a Yandex Maps JSON business record. */
export function mapRecordToCandidate(
  record: Record<string, unknown>,
  origin: string,
): OrganizationCandidate | null {
  const orgId = pickOrgIdFromHref(record);

  if (!orgId) {
    return null;
  }

  const name = pickString(record, [...ORG_NAME_KEYS]);

  if (!name || !isPlausibleOrgName(name)) {
    return null;
  }

  const slug = pickOrgSlug(record, orgId);
  const address = pickString(record, ['address', 'fullAddress', 'formattedAddress', 'subtitle']) ?? '';
  const ratingFields = extractRatingFields(record);

  return {
    org_id: orgId,
    name,
    address,
    ...ratingFields,
    canonical_url: `${origin}/maps/org/${slug}/${orgId}/`,
  };
}

/** DOM selectors for organization title on card pages. */
export const ORG_PAGE_NAME_SELECTORS = [
  '[class*="orgpage-header-view__header"] [class*="title"]',
  '[class*="business-card-title-view__title"]',
  '[class*="card-title-view__title"]',
  '[class*="orgpage-header"] [class*="title"]',
] as const;

/** DOM selectors for organization title in search snippets. */
export const SEARCH_SNIPPET_NAME_SELECTORS = [
  '[class*="search-business-snippet-view__title"]',
  '[class*="search-snippet-view__title"]',
  '[class*="business-card-title-view__title"]',
  '[class*="orgpage-header-view__header"] [class*="title"]',
] as const;
