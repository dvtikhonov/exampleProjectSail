import { describe, expect, it } from 'vitest';
import {
  isPlausibleOrgName,
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
