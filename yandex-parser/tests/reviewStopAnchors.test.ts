import { describe, expect, it } from 'vitest';
import { reviewsMatchAtOffset, shouldStopFetching } from '../src/utils/reviewStopAnchors.js';
import type { ParsedReview } from '../src/types.js';

function review(externalId: string): ParsedReview {
  return {
    external_id: externalId,
    author_name: 'Author',
    published_at: null,
    text: 'Text',
    rating: 5,
  };
}

describe('reviewStopAnchors', () => {
  it('stops when top three match cached anchors', () => {
    const reviews = [review('a'), review('b'), review('c'), review('d')];
    const anchors = ['a', 'b', 'c'];

    expect(shouldStopFetching(reviews, anchors)).toBe(true);
    expect(reviewsMatchAtOffset(reviews, anchors, 0)).toBe(true);
  });

  it('stops when anchors appear after new reviews', () => {
    const reviews = [review('new-1'), review('new-2'), review('a'), review('b'), review('c')];
    const anchors = ['a', 'b', 'c'];

    expect(shouldStopFetching(reviews, anchors)).toBe(true);
    expect(reviewsMatchAtOffset(reviews, anchors, 2)).toBe(true);
  });

  it('continues when anchors are not found yet', () => {
    const reviews = [review('new-1'), review('new-2'), review('a'), review('x')];
    const anchors = ['a', 'b', 'c'];

    expect(shouldStopFetching(reviews, anchors)).toBe(false);
  });

  it('returns false for empty anchors', () => {
    expect(shouldStopFetching([review('a')], [])).toBe(false);
  });
});
