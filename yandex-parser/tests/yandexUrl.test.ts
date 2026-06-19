import { describe, expect, it } from 'vitest';
import { parseReviewsCountFromText } from '../src/utils/yandexUrl.js';

describe('parseReviewsCountFromText', () => {
  it('parses classic "N отзыва" format', () => {
    expect(parseReviewsCountFromText('4,4 · 24 отзыва · 68 оценок')).toBe(24);
    expect(parseReviewsCountFromText('24 отзыва')).toBe(24);
  });

  it('parses Yandex tab format "Отзывы24"', () => {
    expect(parseReviewsCountFromText('Фото11Отзывы24')).toBe(24);
    expect(parseReviewsCountFromText('ОбзорТовары и услугиНовости2Фото11Отзывы24Филиалы')).toBe(24);
  });

  it('parses four-digit tab counter "Отзывы1077" without truncating', () => {
    expect(parseReviewsCountFromText('Фото2Отзывы1077Филиалы')).toBe(1077);
    expect(parseReviewsCountFromText('ОбзорФото11Отзывы1077')).toBe(1077);
  });

  it('does not confuse photo count with reviews', () => {
    expect(parseReviewsCountFromText('11 фото')).toBeNull();
  });
});
