import { pickRecord, pickString } from './jsonExtract.js';
import { parsePublishedAt, parseRating } from './yandexUrl.js';
import type { ParsedReview } from '../types.js';

const AUTHOR_KEYS = ['author', 'user', 'userName', 'authorName'] as const;
const TEXT_KEYS = ['text', 'comment', 'reviewText', 'body', 'snippet'] as const;
const REVIEW_ID_KEYS = ['reviewId', 'review_id', 'externalId', 'uuid'] as const;

const NAVIGATION_REVIEW_IDS = /^(backend_|yandex$|payment_|wheelchair_|pushkin_|privilege_|preliminary_|audio_guide$|tickets$|unavailable$|cash$)/i;

/** Whether a network response URL may contain organization reviews. */
export function isReviewPayloadUrl(url: string): boolean {
  const lower = url.toLowerCase();

  if (
    lower.includes('location-info') ||
    lower.includes('mc.yandex.ru/watch') ||
    lower.includes('/tiles?') ||
    lower.includes('surveys.yandex.ru')
  ) {
    return false;
  }

  return (
    lower.includes('review') ||
    lower.includes('ugc') ||
    lower.includes('business-reviews') ||
    (lower.includes('/maps/api/') && lower.includes('business'))
  );
}

/** Map a JSON record to a review when it looks like a real UGC review. */
export function mapRecordToReview(record: Record<string, unknown>): ParsedReview | null {
  const author = pickString(record, [...AUTHOR_KEYS]);
  const text = pickString(record, [...TEXT_KEYS]);
  const reviewId = pickString(record, [...REVIEW_ID_KEYS]);
  const genericId = pickString(record, ['id']);
  const externalId = reviewId ?? (author && text ? `${author}-${text.slice(0, 32)}` : genericId);

  const ratingRecord = pickRecord(record, ['rating', 'stars', 'score']);
  const rating = parseRating(record.rating ?? record.score ?? ratingRecord?.value ?? ratingRecord?.rating);
  const publishedAt = parsePublishedAt(record.updatedTime ?? record.createdTime ?? record.date ?? record.time);

  if (!externalId) {
    return null;
  }

  if (NAVIGATION_REVIEW_IDS.test(externalId)) {
    return null;
  }

  const nameOnlyLabel = !author ? pickString(record, ['name']) : null;

  if (nameOnlyLabel && !text && rating === null) {
    return null;
  }

  if (!text && rating === null) {
    return null;
  }

  if (!text && !author) {
    return null;
  }

  const resolvedAuthor = author ?? nameOnlyLabel ?? 'Аноним';

  if (!text && resolvedAuthor.length <= 40 && !reviewId && publishedAt === null) {
    return null;
  }

  return {
    external_id: externalId,
    author_name: resolvedAuthor,
    published_at: publishedAt,
    text,
    rating,
  };
}

/** Keep DOM reviews that look like real user feedback. */
export function isPlausibleDomReview(review: ParsedReview): boolean {
  const text = review.text?.trim() ?? '';
  const author = review.author_name.trim();

  if (text === 'Подписаться' || text === 'Без текста') {
    return false;
  }

  if (text.length >= 15) {
    return true;
  }

  if (review.rating !== null && author !== '' && author !== 'Аноним' && text.length >= 5) {
    return true;
  }

  return false;
}

/** Normalize and deduplicate DOM reviews. */
export function normalizeDomReviews(reviews: ParsedReview[]): ParsedReview[] {
  const normalized: ParsedReview[] = [];

  for (const review of reviews) {
    if (!isPlausibleDomReview(review)) {
      continue;
    }

    const text = review.text?.trim() ?? null;
    const author = review.author_name
      .replace(/Знаток города.*$/i, '')
      .replace(/Подписаться$/i, '')
      .trim();

    normalized.push({
      ...review,
      author_name: author || 'Аноним',
      text,
      external_id: review.external_id.startsWith('dom-')
        ? `${author || 'anon'}-${text?.slice(0, 40) ?? review.external_id}`
        : review.external_id,
    });
  }

  return dedupeReviews(normalized);
}

/** Deduplicate reviews by external id. */
export function dedupeReviews(reviews: ParsedReview[]): ParsedReview[] {
  const map = new Map<string, ParsedReview>();

  for (const review of reviews) {
    map.set(review.external_id, review);
  }

  return Array.from(map.values());
}

function reviewQualityScore(review: ParsedReview): number {
  let score = 0;

  if (review.author_name.trim() !== '' && review.author_name !== 'Аноним') {
    score += 2;
  }

  if (review.published_at) {
    score += 1;
  }

  if (review.rating !== null) {
    score += 1;
  }

  return score;
}

/** Collapse DOM and page-state duplicates that share the same review text. */
export function dedupeReviewsByContent(reviews: ParsedReview[]): ParsedReview[] {
  const map = new Map<string, ParsedReview>();

  for (const review of reviews) {
    const textKey = (review.text ?? '').replace(/\s+/g, ' ').trim().slice(0, 120).toLowerCase();
    const key = textKey.length >= 15 ? `text:${textKey}` : `id:${review.external_id}`;

    const existing = map.get(key);

    if (!existing || reviewQualityScore(review) > reviewQualityScore(existing)) {
      map.set(key, review);
    }
  }

  return Array.from(map.values());
}
