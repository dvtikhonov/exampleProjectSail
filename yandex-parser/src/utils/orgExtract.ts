/**
 * Извлечение полей организации из JSON Яндекс.Карт и общие DOM-селекторы.
 */
import { pickRecord, pickString } from './jsonExtract.js';
import { extractOrgIdFromHref, extractOrgIdFromUrl, parseCount, parseRating } from './yandexUrl.js';

const HREF_KEYS = ['uri', 'url', 'link', 'permalink', 'canonicalUrl'] as const;
const ORG_NAME_KEYS = ['shortName', 'name', 'title', 'caption', 'fullName'] as const;

/** Отсечь текст вкладок («Обзор», «Отзывы12») ошибочно принятый за название. */
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

/** Извлечь org_id только из путей /org/.../{id} в href-подобных полях. */
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

/** Принадлежит ли JSON-запись запрошенной организации (href или id + признаки карточки). */
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

  // id без href/name/address часто встречается в нерелевантных узлах — требуем «признаки» организации.
  return (
    pickRecord(record, ['ratingData', 'rating', 'stars']) !== null ||
    pickString(record, ['address', 'fullAddress', 'formattedAddress']) !== null ||
    pickString(record, [...ORG_NAME_KEYS]) !== null
  );
}

/** Счётчики рейтинга из записи бизнеса Яндекс.Карт. */
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

/** Селекторы названия на странице карточки организации. */
export const ORG_PAGE_NAME_SELECTORS = [
  '[class*="orgpage-header-view__header"] [class*="title"]',
  '[class*="business-card-title-view__title"]',
  '[class*="card-title-view__title"]',
  '[class*="orgpage-header"] [class*="title"]',
] as const;

/** Селекторы адреса на карточке и странице отзывов. */
export const ORG_PAGE_ADDRESS_SELECTORS = [
  '[class*="orgpage-header"] [class*="address"]',
  '[class*="business-contacts"] [class*="address"]',
  '[class*="toponym"]',
  '[class*="business-card-view__address"]',
  '[class*="search-snippet-view__address"]',
].join(', ');

/** Селекторы названия в сниппетах поисковой выдачи. */
export const SEARCH_SNIPPET_NAME_SELECTORS = [
  '[class*="search-business-snippet-view__title"]',
  '[class*="search-snippet-view__title"]',
  '[class*="business-card-title-view__title"]',
  '[class*="orgpage-header-view__header"] [class*="title"]',
] as const;
