import { describe, expect, it } from 'vitest';
import type { DomOrgHarvest, PageMeta, ResolveCollectResponseBody } from '../src/types.js';

const invitroDomHarvest: DomOrgHarvest = {
  href: '/maps/org/invitro/11527230587/',
  link_text: 'Invitro',
  card_text: 'ул. Тореза, 61 ... 24 отзыва',
  rating_aria_label: 'рейтинг 4,4',
  meta_text: 'ул. Тореза, 61, Новокузнецк',
};

const invitroPageMeta: PageMeta = {
  title: 'Invitro — Яндекс Карты',
  header_text: 'Invitro',
  address_text: 'ул. Тореза, 61, Новокузнецк',
};

function isDomOrgHarvest(value: unknown): value is DomOrgHarvest {
  if (!value || typeof value !== 'object') {
    return false;
  }

  const record = value as Record<string, unknown>;

  return (
    typeof record.href === 'string' &&
    typeof record.link_text === 'string' &&
    typeof record.card_text === 'string' &&
    typeof record.rating_aria_label === 'string' &&
    typeof record.meta_text === 'string'
  );
}

function isPageMeta(value: unknown): value is PageMeta {
  if (!value || typeof value !== 'object') {
    return false;
  }

  const record = value as Record<string, unknown>;

  return (
    typeof record.title === 'string' &&
    typeof record.header_text === 'string' &&
    typeof record.address_text === 'string'
  );
}

function isResolveCollectResponseBody(value: unknown): value is ResolveCollectResponseBody {
  if (!value || typeof value !== 'object') {
    return false;
  }

  const record = value as Record<string, unknown>;

  return (
    typeof record.resolved_url === 'string' &&
    typeof record.is_direct_org === 'boolean' &&
    (record.direct_org_id === null || typeof record.direct_org_id === 'string') &&
    Array.isArray(record.network_payloads) &&
    Array.isArray(record.dom_harvest) &&
    record.dom_harvest.every(isDomOrgHarvest) &&
    isPageMeta(record.page_meta)
  );
}

describe('ResolveCollectResponseBody contract', () => {
  it('accepts search collect snapshot', () => {
    const body: ResolveCollectResponseBody = {
      resolved_url: 'https://yandex.ru/maps/?text=invitro',
      is_direct_org: false,
      direct_org_id: null,
      network_payloads: [{ data: { items: [] } }],
      dom_harvest: [invitroDomHarvest],
      page_meta: invitroPageMeta,
    };

    expect(isResolveCollectResponseBody(body)).toBe(true);
  });

  it('accepts direct org collect snapshot', () => {
    const body: ResolveCollectResponseBody = {
      resolved_url: 'https://yandex.ru/maps/org/invitro/11527230587/',
      is_direct_org: true,
      direct_org_id: '11527230587',
      network_payloads: [],
      dom_harvest: [invitroDomHarvest],
      page_meta: invitroPageMeta,
    };

    expect(isResolveCollectResponseBody(body)).toBe(true);
    expect(body.is_direct_org).toBe(true);
    expect(body.direct_org_id).toBe('11527230587');
  });

  it('rejects legacy candidates response shape', () => {
    expect(
      isResolveCollectResponseBody({
        resolved_url: 'https://yandex.ru/maps/',
        candidates: [],
      }),
    ).toBe(false);
  });
});
