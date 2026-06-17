import { walkJson } from './jsonExtract.js';
import { dedupeReviews, mapRecordToReview } from './reviewExtract.js';
import type { ParsedReview } from '../types.js';

/** Parse embedded JSON blobs from the initial HTML page. */
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

/** Extract reviews from embedded page state JSON. */
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
