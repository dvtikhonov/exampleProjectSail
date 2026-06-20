import { describe, expect, it } from 'vitest';
import {
  dedupeReviewsByContent,
  isPlausibleDomReview,
  isReviewPayloadUrl,
  mapRecordToReview,
  normalizeDomReviews,
  unifyReviewsByContentPreferApiIds,
} from '../src/utils/reviewExtract.js';
import type { ParsedReview } from '../src/types.js';

describe('isReviewPayloadUrl', () => {
  it('rejects location discovery payloads', () => {
    expect(isReviewPayloadUrl('https://yandex.ru/maps/api/location-info/get?ajax=1')).toBe(false);
  });

  it('accepts review api payloads', () => {
    expect(isReviewPayloadUrl('https://yandex.ru/maps/api/business-reviews/get')).toBe(true);
  });
});

describe('mapRecordToReview', () => {
  it('rejects discovery navigation item', () => {
    expect(
      mapRecordToReview({
        id: 'backend_CHZ_TOURISM',
        name: 'Чем заняться',
      }),
    ).toBeNull();
  });

  it('accepts review with text and author', () => {
    expect(
      mapRecordToReview({
        reviewId: 'rev-1',
        authorName: 'Галина',
        text: 'Этот медофис посещаю довольно регулярно',
        rating: 5,
      }),
    ).toEqual({
      external_id: 'rev-1',
      author_name: 'Галина',
      published_at: null,
      text: 'Этот медофис посещаю довольно регулярно',
      rating: 5,
    });
  });
});

describe('normalizeDomReviews', () => {
  it('keeps long review text and strips badge noise from author', () => {
    const reviews = normalizeDomReviews([
      {
        external_id: 'dom-1',
        author_name: 'ГалинаЗнаток города 6 уровняПодписаться',
        published_at: '17 августа 2025',
        text: 'Этот медофис посещаю довольно регулярно, все нравится!',
        rating: 5,
      },
      {
        external_id: 'dom-2',
        author_name: 'Чем заняться',
        published_at: null,
        text: null,
        rating: null,
      },
    ]);

    expect(reviews).toHaveLength(1);
    expect(reviews[0]?.author_name).toBe('Галина');
    expect(reviews[0]?.text).toContain('медофис');
  });
});

describe('isPlausibleDomReview', () => {
  it('rejects subscribe button text', () => {
    expect(
      isPlausibleDomReview({
        external_id: 'x',
        author_name: 'Галина',
        published_at: null,
        text: 'Подписаться',
        rating: null,
      }),
    ).toBe(false);
  });
});

describe('dedupeReviewsByContent', () => {
  it('keeps one review per text and prefers named author', () => {
    const reviews = dedupeReviewsByContent([
      {
        external_id: 'dom-1',
        author_name: 'Аноним',
        published_at: null,
        text: 'Этот медофис посещаю довольно регулярно, все нравится!',
        rating: null,
      },
      {
        external_id: 'state-1',
        author_name: 'Галина',
        published_at: '17 августа 2023',
        text: 'Этот медофис посещаю довольно регулярно, все нравится!',
        rating: 5,
      },
    ]);

    expect(reviews).toHaveLength(1);
    expect(reviews[0]?.author_name).toBe('Галина');
  });

  it('keeps rating-only reviews by external id', () => {
    const reviews = dedupeReviewsByContent([
      {
        external_id: 'rev-short-1',
        author_name: 'Иван',
        published_at: '1 января 2026',
        text: 'Ок',
        rating: 4,
      },
      {
        external_id: 'rev-no-text',
        author_name: 'Мария',
        published_at: '2 января 2026',
        text: null,
        rating: 5,
      },
    ]);

    expect(reviews).toHaveLength(2);
  });
});

describe('unifyReviewsByContentPreferApiIds', () => {
  const sharedText = 'Этот медофис посещаю довольно регулярно и всегда доволен';

  const synthetic: ParsedReview = {
    external_id: `Аноним-${sharedText.slice(0, 40)}`,
    author_name: 'Аноним',
    published_at: null,
    text: sharedText,
    rating: 5,
  };

  const api: ParsedReview = {
    external_id: 'Kl5w-CHJ0EhBH7M6dbGAanejbmaghFp',
    author_name: 'Аноним',
    published_at: '1 января 2026',
    text: sharedText,
    rating: 5,
  };

  it('replaces synthetic external_id with API id at first occurrence', () => {
    const unified = unifyReviewsByContentPreferApiIds([synthetic, api]);

    expect(unified).toHaveLength(1);
    expect(unified[0]?.external_id).toBe('Kl5w-CHJ0EhBH7M6dbGAanejbmaghFp');
  });
});
