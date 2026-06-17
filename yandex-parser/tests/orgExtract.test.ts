import { describe, expect, it } from 'vitest';
import {
  isPlausibleOrgName,
  mapRecordToCandidate,
  mergeOrganizationCandidate,
  pickOrgIdFromHref,
  recordMatchesOrgId,
} from '../src/utils/orgExtract.js';

describe('isPlausibleOrgName', () => {
  it('accepts regular business names', () => {
    expect(isPlausibleOrgName('Invitro')).toBe(true);
    expect(isPlausibleOrgName('Кафе X')).toBe(true);
  });

  it('rejects tab navigation concatenation', () => {
    expect(isPlausibleOrgName('ОбзорТовары и услугиНовости2Фото11Отзывы24ФилиалыОсобенности')).toBe(false);
  });
});

describe('pickOrgIdFromHref', () => {
  it('extracts id from uri field', () => {
    expect(
      pickOrgIdFromHref({
        uri: '/maps/org/invitro/11527230587/',
        name: 'Invitro',
      }),
    ).toBe('11527230587');
  });

  it('ignores bare numeric id without org path', () => {
    expect(
      pickOrgIdFromHref({
        id: 115272305870,
        name: 'Wrong entity',
      }),
    ).toBeNull();
  });
});

describe('recordMatchesOrgId', () => {
  it('matches by href org path', () => {
    expect(
      recordMatchesOrgId(
        {
          uri: '/maps/org/invitro/11527230587/',
          name: 'Invitro',
          ratingData: { score: 4.4 },
        },
        '11527230587',
      ),
    ).toBe(true);
  });

  it('does not match unrelated numeric id', () => {
    expect(
      recordMatchesOrgId(
        {
          id: 115272305870,
          name: 'Tab counter',
        },
        '11527230587',
      ),
    ).toBe(false);
  });
});

describe('mapRecordToCandidate', () => {
  it('maps business record from href', () => {
    const candidate = mapRecordToCandidate(
      {
        uri: '/maps/org/invitro/11527230587/',
        shortName: 'Invitro',
        address: 'ул. Тореза, 61, Новокузнецк',
        ratingData: {
          score: 4.4,
          ratingsCount: 68,
          reviewsCount: 24,
        },
      },
      'https://yandex.ru',
    );

    expect(candidate).toEqual({
      org_id: '11527230587',
      name: 'Invitro',
      address: 'ул. Тореза, 61, Новокузнецк',
      average_rating: 4.4,
      ratings_count: 68,
      reviews_count: 24,
      canonical_url: 'https://yandex.ru/maps/org/invitro/11527230587/',
    });
  });
});

describe('mergeOrganizationCandidate', () => {
  it('prefers API name over DOM tab navigation', () => {
    const merged = mergeOrganizationCandidate(
      {
        org_id: '11527230587',
        name: 'Invitro',
        address: 'ул. Тореза, 61',
        average_rating: 4.4,
        reviews_count: 24,
        ratings_count: 68,
        canonical_url: 'https://yandex.ru/maps/org/invitro/11527230587/',
      },
      {
        org_id: '11527230587',
        name: 'ОбзорТовары и услугиНовости2Фото11Отзывы24ФилиалыОсобенности',
        address: '',
        average_rating: 4.5,
        reviews_count: 11,
        ratings_count: null,
        canonical_url: 'https://yandex.ru/maps/org/invitro/11527230587/',
      },
      '11527230587',
    );

    expect(merged?.name).toBe('Invitro');
    expect(merged?.average_rating).toBe(4.4);
    expect(merged?.ratings_count).toBe(68);
    expect(merged?.reviews_count).toBe(24);
  });
});
