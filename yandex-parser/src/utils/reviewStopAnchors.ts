/**
 * Логика stop_anchors: прекращать скролл, когда верх кэша найден в текущем списке отзывов.
 */
import type { ParsedReview } from '../types.js';

/** Проверить, что подряд идущие external_id с offset совпадают со stop_anchors. */
export function reviewsMatchAtOffset(
  reviews: ParsedReview[],
  stopAnchors: string[],
  offset: number,
): boolean {
  if (stopAnchors.length === 0 || offset < 0) {
    return false;
  }

  for (let index = 0; index < stopAnchors.length; index += 1) {
    if (reviews[offset + index]?.external_id !== stopAnchors[index]) {
      return false;
    }
  }

  return true;
}

/** true, если последовательность stop_anchors встречается в reviews — дальше листать не нужно. */
export function shouldStopFetching(reviews: ParsedReview[], stopAnchors: string[]): boolean {
  if (stopAnchors.length === 0) {
    return false;
  }

  const maxOffset = reviews.length - stopAnchors.length;

  for (let offset = 0; offset <= maxOffset; offset += 1) {
    if (reviewsMatchAtOffset(reviews, stopAnchors, offset)) {
      return true;
    }
  }

  return false;
}
