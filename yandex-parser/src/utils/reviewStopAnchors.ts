import type { ParsedReview } from '../types.js';

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

/** True when cached top reviews are found in the incoming list — stop scrolling further. */
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
