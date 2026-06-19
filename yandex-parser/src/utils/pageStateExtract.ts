/**
 * Встроенный в HTML JSON (script[type=application/json]) — часто содержит отзывы до XHR.
 */
import { walkJson } from './jsonExtract.js';
import { dedupeReviews, mapRecordToReview } from './reviewExtract.js';
import type { ParsedReview } from '../types.js';

/** Прочитать script[type=application/json] из начального HTML. */
export async function collectEmbeddedPageState(
  page: { evaluate: (fn: () => unknown) => Promise<unknown> },
): Promise<unknown[]> {
  const payloads = await page.evaluate(() => {
    const results: unknown[] = [];

    document.querySelectorAll('script[type="application/json"]').forEach((element) => {
      try {
        results.push(JSON.parse(element.textContent ?? ''));
      } catch {
        // Ignore invalid JSON blocks.
      }
    });

    return results;
  });

  return Array.isArray(payloads) ? payloads : [];
}

/** Отзывы из встроенного page state через walkJson + mapRecordToReview. */
export function extractReviewsFromPageState(payloads: unknown[]): ParsedReview[] {
  const reviews: ParsedReview[] = [];

  for (const payload of payloads) {
    walkJson(payload, (record) => {
      const review = mapRecordToReview(record);

      if (review) {
        reviews.push(review);
      }
    });
  }

  return dedupeReviews(reviews);
}
