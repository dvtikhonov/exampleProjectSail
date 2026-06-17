import type { Page } from 'playwright';
import { humanMouseJiggle } from '../humanMouseJiggle.js';
import { config } from '../config.js';
import { NetworkJsonCollector, pickString, walkJson } from '../utils/jsonExtract.js';
import {
  extractOrgIdFromUrl,
  isDirectOrgUrl,
  normalizeOrgUrl,
} from '../utils/yandexUrl.js';
import {
  isPlausibleOrgName,
  mapRecordToCandidate,
  mergeOrganizationCandidate,
  ORG_PAGE_NAME_SELECTORS,
  SEARCH_SNIPPET_NAME_SELECTORS,
} from '../utils/orgExtract.js';
import type { OrganizationCandidate } from '../types.js';
import { createContext, gotoWithJiggle, scrollWithJiggle, waitForSelectorWithJiggle } from '../browser.js';

interface ResolveOptions {
  candidateLimit?: number;
}

/**
 * Resolve Yandex Maps URL to organization candidates.
 * Follows redirects, handles direct org cards and search result pages.
 */
export async function resolveOrganization(
  inputUrl: string,
  options: ResolveOptions = {},
): Promise<{ resolved_url: string; candidates: OrganizationCandidate[] }> {
  const candidateLimit = options.candidateLimit ?? config.resolveCandidateLimit;
  const context = await createContext();
  const page = await context.newPage();
  const collector = new NetworkJsonCollector();
  collector.attach(page);

  try {
    await gotoWithJiggle(page, inputUrl);
    await page.waitForTimeout(1500);

    const resolvedUrl = page.url();
    const fromDirectUrl = extractOrgIdFromUrl(resolvedUrl);

    if (fromDirectUrl && isDirectOrgUrl(resolvedUrl)) {
      const candidate = await extractDirectOrgCandidate(page, resolvedUrl, fromDirectUrl, collector);
      return {
        resolved_url: resolvedUrl,
        candidates: candidate ? [candidate] : [],
      };
    }

    await waitForSelectorWithJiggle(page, 'body');
    await collectSearchCandidatesFromScroll(page, candidateLimit);

    const candidates = await mergeCandidates(
      page,
      resolvedUrl,
      collector.getPayloads(),
      candidateLimit,
    );

    return {
      resolved_url: resolvedUrl,
      candidates,
    };
  } finally {
    await page.close();
    await context.close();
  }
}

async function extractDirectOrgCandidate(
  page: Page,
  resolvedUrl: string,
  orgId: string,
  collector: NetworkJsonCollector,
): Promise<OrganizationCandidate | null> {
  await humanMouseJiggle(page, {
    minPx: config.mouseJiggleMinPx,
    maxPx: config.mouseJiggleMaxPx,
  });

  const apiCandidate = extractCandidatesFromPayloads(collector.getPayloads(), resolvedUrl).find(
    (item) => item.org_id === orgId,
  );
  const domCandidate = await extractCandidateFromDom(page, resolvedUrl, orgId);

  const merged = mergeOrganizationCandidate(apiCandidate, domCandidate, orgId);

  if (merged) {
    return merged;
  }

  return {
    org_id: orgId,
    name: await readOrgNameFromPage(page),
    address: await readOrgAddressFromPage(page),
    average_rating: null,
    reviews_count: null,
    ratings_count: null,
    canonical_url: normalizeOrgUrl(resolvedUrl, orgId),
  };
}

async function collectSearchCandidatesFromScroll(page: Page, candidateLimit: number): Promise<void> {
  const maxScrolls = Math.ceil(candidateLimit / 5);

  for (let i = 0; i < maxScrolls; i += 1) {
    await scrollWithJiggle(page, 900);
    await page.waitForTimeout(config.syncScrollDelayMs);
  }
}

async function mergeCandidates(
  page: Page,
  resolvedUrl: string,
  payloads: unknown[],
  candidateLimit: number,
): Promise<OrganizationCandidate[]> {
  await humanMouseJiggle(page, {
    minPx: config.mouseJiggleMinPx,
    maxPx: config.mouseJiggleMaxPx,
  });

  const fromNetwork = extractCandidatesFromPayloads(payloads, resolvedUrl);
  const fromDom = await extractCandidatesFromDom(page, resolvedUrl);

  const merged = new Map<string, OrganizationCandidate>();

  for (const candidate of fromDom) {
    const existing = merged.get(candidate.org_id);
    merged.set(
      candidate.org_id,
      existing ? mergeOrganizationCandidate(existing, candidate) ?? candidate : candidate,
    );
  }

  for (const candidate of fromNetwork) {
    const existing = merged.get(candidate.org_id);
    merged.set(
      candidate.org_id,
      existing ? mergeOrganizationCandidate(candidate, existing) ?? candidate : candidate,
    );
  }

  return Array.from(merged.values()).slice(0, candidateLimit);
}

function extractCandidatesFromPayloads(payloads: unknown[], resolvedUrl: string): OrganizationCandidate[] {
  const candidates: OrganizationCandidate[] = [];
  const origin = safeOrigin(resolvedUrl);

  for (const payload of payloads) {
    walkJson(payload, (record) => {
      const candidate = mapRecordToCandidate(record, origin);

      if (candidate) {
        candidates.push(candidate);
      }
    });
  }

  return dedupeCandidates(candidates);
}

async function extractCandidatesFromDom(page: Page, resolvedUrl: string): Promise<OrganizationCandidate[]> {
  const origin = safeOrigin(resolvedUrl);

  return page.evaluate(
    ({ baseOrigin, nameSelectors }) => {
      const isPlausibleName = (name: string): boolean => {
        const trimmed = name.trim();

        if (!trimmed || trimmed.length > 100) {
          return false;
        }

        if (/Обзор.*(?:Товары|Фото|Отзывы|Филиалы|Особенности)/i.test(trimmed)) {
          return false;
        }

        if (/^(Обзор|Товары и услуги|Новости\d*|Фото\d*|Отзывы\d*|Филиалы|Особенности)/i.test(trimmed) && trimmed.length > 25) {
          return false;
        }

        return true;
      };

      const results: OrganizationCandidate[] = [];
      const links = Array.from(document.querySelectorAll('a[href*="/org/"]'));

      for (const link of links) {
        const href = link.getAttribute('href') ?? '';
        const match = href.match(/\/org\/[^/]+\/(\d+)(?:\/|$|\?|#)/i);

        if (!match) {
          continue;
        }

        const extraPath = href.match(/\/org\/[^/]+\/\d+(\/[^/?#]+)/i)?.[1] ?? '';

        if (extraPath && extraPath !== '/') {
          continue;
        }

        const orgId = match[1];
        const card = link.closest(
          '[class*="search-snippet"], [class*="search-business-snippet"], [class*="snippet-view"], li, article',
        );

        if (!card) {
          continue;
        }

        let nameElement: Element | null = null;

        for (const selector of nameSelectors) {
          nameElement = card.querySelector(selector);

          if (nameElement) {
            break;
          }
        }

        const name = (nameElement?.textContent ?? link.textContent ?? '').trim();

        if (!isPlausibleName(name)) {
          continue;
        }

        const addressElement = card.querySelector(
          '[class*="search-snippet-view__address"], [class*="business-card-view__address"], [class*="address"], [class*="subtitle"], [class*="geo"]',
        );
        const ratingElement = card.querySelector(
          '[class*="rating"], [class*="stars"], [aria-label*="рейтинг"], [aria-label*="оцен"]',
        );

        const slugMatch = href.match(/\/org\/([^/]+)\/\d+/i);
        const slug = slugMatch?.[1] ?? 'organization';
        const ratingText = ratingElement?.getAttribute('aria-label') ?? ratingElement?.textContent ?? '';
        const ratingMatch = ratingText.replace(',', '.').match(/(\d+(?:\.\d+)?)/);
        const reviewsMatch = card.textContent?.match(/(\d[\d\s]*)\s*отзыв/i);
        const ratingsMatch = card.textContent?.match(/(\d[\d\s]*)\s*оцен/i);

        let address = (addressElement?.textContent ?? '').trim();

        if (!address) {
          const metaElement = card.querySelector('[class*="meta"], [class*="caption"], [class*="subtitle"]');
          const metaText = (metaElement?.textContent ?? '').trim();

          if (metaText && metaText !== name) {
            address = metaText;
          }
        }

        results.push({
          org_id: orgId,
          name,
          address,
          average_rating: ratingMatch ? Number.parseFloat(ratingMatch[1]) : null,
          reviews_count: reviewsMatch
            ? Number.parseInt(reviewsMatch[1].replace(/\s/g, ''), 10)
            : null,
          ratings_count: ratingsMatch
            ? Number.parseInt(ratingsMatch[1].replace(/\s/g, ''), 10)
            : null,
          canonical_url: `${baseOrigin}/maps/org/${slug}/${orgId}/`,
        });
      }

      return results;
    },
    { baseOrigin: origin, nameSelectors: [...SEARCH_SNIPPET_NAME_SELECTORS] },
  );
}

async function extractCandidateFromDom(
  page: Page,
  resolvedUrl: string,
  orgId: string,
): Promise<OrganizationCandidate | null> {
  const candidates = await extractCandidatesFromDom(page, resolvedUrl);
  const fromSearch = candidates.find((item) => item.org_id === orgId);

  if (fromSearch) {
    return fromSearch;
  }

  return readOrgCandidateFromCardPage(page, resolvedUrl, orgId);
}

async function readOrgCandidateFromCardPage(
  page: Page,
  resolvedUrl: string,
  orgId: string,
): Promise<OrganizationCandidate | null> {
  const origin = safeOrigin(resolvedUrl);
  const data = await page.evaluate((nameSelectors) => {
    const isPlausibleName = (name: string): boolean => {
      const trimmed = name.trim();

      if (!trimmed || trimmed.length > 100) {
        return false;
      }

      if (/Обзор.*(?:Товары|Фото|Отзывы|Филиалы|Особенности)/i.test(trimmed)) {
        return false;
      }

      if (/^(Обзор|Товары и услуги|Новости\d*|Фото\d*|Отзывы\d*|Филиалы|Особенности)/i.test(trimmed) && trimmed.length > 25) {
        return false;
      }

      return true;
    };

    let name = '';

    for (const selector of nameSelectors) {
      const text = document.querySelector(selector)?.textContent?.trim();

      if (text && isPlausibleName(text)) {
        name = text;
        break;
      }
    }

    if (!name) {
      const title = document.title.replace(/\s*—\s*Яндекс\.?\s*Карты.*/i, '').trim();

      if (isPlausibleName(title)) {
        name = title;
      }
    }

    const address =
      document.querySelector(
        '[class*="orgpage-header"] [class*="address"], [class*="business-contacts"] [class*="address"], [class*="toponym"]',
      )?.textContent?.trim() ?? '';

    const ratingBlock = document.querySelector('[class*="business-rating"], [class*="orgpage-header"] [class*="rating"]');
    const ratingText = ratingBlock?.textContent?.replace(',', '.') ?? '';
    const ratingMatch = ratingText.match(/(\d+(?:\.\d+)?)/);
    const headerText =
      document.querySelector('[class*="orgpage-header"], [class*="business-card-view"]')?.textContent ?? '';
    const reviewsMatch = headerText.match(/(\d[\d\s]*)\s*отзыв/i);
    const ratingsMatch = headerText.match(/(\d[\d\s]*)\s*оцен/i);

    return {
      name,
      address,
      average_rating: ratingMatch ? Number.parseFloat(ratingMatch[1]) : null,
      reviews_count: reviewsMatch ? Number.parseInt(reviewsMatch[1].replace(/\s/g, ''), 10) : null,
      ratings_count: ratingsMatch ? Number.parseInt(ratingsMatch[1].replace(/\s/g, ''), 10) : null,
    };
  }, [...ORG_PAGE_NAME_SELECTORS]);

  if (!data.name) {
    return null;
  }

  const slugMatch = resolvedUrl.match(/\/org\/([^/]+)\/\d+/i);
  const slug = slugMatch?.[1] ?? 'organization';

  return {
    org_id: orgId,
    name: data.name,
    address: data.address,
    average_rating: data.average_rating,
    reviews_count: data.reviews_count,
    ratings_count: data.ratings_count,
    canonical_url: `${origin}/maps/org/${slug}/${orgId}/`,
  };
}

async function readOrgNameFromPage(page: Page): Promise<string> {
  const name = await page.evaluate((nameSelectors) => {
    const isPlausibleName = (value: string): boolean => {
      const trimmed = value.trim();

      if (!trimmed || trimmed.length > 100) {
        return false;
      }

      if (/Обзор.*(?:Товары|Фото|Отзывы|Филиалы|Особенности)/i.test(trimmed)) {
        return false;
      }

      if (/^(Обзор|Товары и услуги|Новости\d*|Фото\d*|Отзывы\d*|Филиалы|Особенности)/i.test(trimmed) && trimmed.length > 25) {
        return false;
      }

      return true;
    };

    for (const selector of nameSelectors) {
      const text = document.querySelector(selector)?.textContent?.trim();

      if (text && isPlausibleName(text)) {
        return text;
      }
    }

    const title = document.title.replace(/\s*—\s*Яндекс\.?\s*Карты.*/i, '').trim();

    return isPlausibleName(title) ? title : '';
  }, [...ORG_PAGE_NAME_SELECTORS]);

  return name;
}

async function readOrgAddressFromPage(page: Page): Promise<string> {
  return page.evaluate(() => {
    const selectors = [
      '[class*="orgpage-header"] [class*="address"]',
      '[class*="business-contacts"] [class*="address"]',
      '[class*="toponym"]',
    ];

    for (const selector of selectors) {
      const element = document.querySelector(selector);
      const text = element?.textContent?.trim();

      if (text) {
        return text;
      }
    }

    return '';
  });
}

function dedupeCandidates(candidates: OrganizationCandidate[]): OrganizationCandidate[] {
  const map = new Map<string, OrganizationCandidate>();

  for (const candidate of candidates) {
    const existing = map.get(candidate.org_id);
    map.set(
      candidate.org_id,
      existing ? mergeOrganizationCandidate(candidate, existing) ?? candidate : candidate,
    );
  }

  return Array.from(map.values()).filter((candidate) => isPlausibleOrgName(candidate.name));
}

function safeOrigin(url: string): string {
  try {
    return new URL(url).origin;
  } catch {
    return 'https://yandex.ru';
  }
}
