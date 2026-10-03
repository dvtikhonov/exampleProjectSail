import { describe, expect, it } from 'vitest';
import {
  buildItemGetListParams,
  buildQueryString,
  matchesSearchText,
} from '../src/client/query.js';

describe('buildItemGetListParams', () => {
  it('не включает filters[category_id] (поля fields[category_id] допустимы)', () => {
    const params = buildItemGetListParams({ page: 1, limit: 50 });
    const qs = buildQueryString(params);

    expect(qs).not.toMatch(/filters%5Bcategory_id/);
    expect(qs).not.toMatch(/filters%5Bcategory%5D/);
    expect(qs).toMatch(/filters%5Bparent_id%5D%5B0%5D=0/);
    expect(qs).toMatch(/page=1/);
    expect(qs).toMatch(/limit=50/);
    expect(qs).toMatch(/fields%5Bname%5D=name/);
    expect(qs).toMatch(/fields%5Bcategory_id%5D=category_id/);
  });
});

describe('matchesSearchText', () => {
  it('фильтрует подстроку без учёта регистра', () => {
    expect(matchesSearchText('Куриное Филе', 'филе')).toBe(true);
    expect(matchesSearchText('Куриное Филе', 'рис')).toBe(false);
    expect(matchesSearchText('X', '')).toBe(true);
    expect(matchesSearchText('X', null)).toBe(true);
  });
});
