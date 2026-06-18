import type { Page } from 'playwright';
import type { DomOrgHarvest, PageMeta } from '../types.js';
import {
  ORG_PAGE_ADDRESS_SELECTORS,
  ORG_PAGE_NAME_SELECTORS,
  SEARCH_SNIPPET_NAME_SELECTORS,
} from '../utils/orgExtract.js';

const SEARCH_CARD_SELECTORS =
  '[class*="search-snippet"], [class*="search-business-snippet"], [class*="snippet-view"], li, article';

const ADDRESS_SELECTORS =
  '[class*="search-snippet-view__address"], [class*="business-card-view__address"], [class*="address"], [class*="subtitle"], [class*="geo"]';

const RATING_SELECTORS =
  '[class*="rating"], [class*="stars"], [aria-label*="рейтинг"], [aria-label*="оцен"]';

const META_SELECTORS = '[class*="meta"], [class*="caption"], [class*="subtitle"]';

const ORG_PAGE_HEADER_SELECTORS = '[class*="orgpage-header"], [class*="business-card-view"]';

/** Collect raw organization snippets from a Yandex Maps search results page. */
export async function harvestDomFromSearchPage(page: Page): Promise<DomOrgHarvest[]> {
  return page.evaluate(
    ({ nameSelectors, cardSelector, addressSelector, ratingSelector, metaSelector }) => {
      const results: DomOrgHarvest[] = [];
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

        const card = link.closest(cardSelector);

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

        const link_text = (nameElement?.textContent ?? link.textContent ?? '').trim();
        const addressElement = card.querySelector(addressSelector);
        const ratingElement = card.querySelector(ratingSelector);
        const metaElement = card.querySelector(metaSelector);
        const meta_text = (metaElement?.textContent ?? '').trim();
        const address_text = (addressElement?.textContent ?? '').trim();

        results.push({
          href,
          link_text,
          card_text: (card.textContent ?? '').trim(),
          rating_aria_label:
            ratingElement?.getAttribute('aria-label') ?? ratingElement?.textContent ?? '',
          meta_text: meta_text && meta_text !== link_text ? meta_text : address_text,
        });
      }

      return results;
    },
    {
      nameSelectors: [...SEARCH_SNIPPET_NAME_SELECTORS],
      cardSelector: SEARCH_CARD_SELECTORS,
      addressSelector: ADDRESS_SELECTORS,
      ratingSelector: RATING_SELECTORS,
      metaSelector: META_SELECTORS,
    },
  );
}

/** Collect a minimal DOM harvest entry from a direct organization card page. */
export async function harvestDirectOrgDom(page: Page, resolvedUrl: string): Promise<DomOrgHarvest[]> {
  let href = resolvedUrl;

  try {
    const parsed = new URL(resolvedUrl);
    href = `${parsed.pathname}${parsed.search}`;
  } catch {
    // Keep original href when URL parsing fails.
  }

  const data = await page.evaluate(
    ({ nameSelectors, addressSelector, ratingSelector, headerSelector }) => {
      let link_text = '';

      for (const selector of nameSelectors) {
        const text = document.querySelector(selector)?.textContent?.trim();

        if (text) {
          link_text = text;
          break;
        }
      }

      if (!link_text) {
        link_text = document.title.replace(/\s*—\s*Яндекс\.?\s*Карты.*/i, '').trim();
      }

      const addressElement = document.querySelector(addressSelector);
      const ratingElement = document.querySelector(ratingSelector);
      const headerElement = document.querySelector(headerSelector);

      return {
        link_text,
        card_text: headerElement?.textContent?.trim() ?? '',
        rating_aria_label:
          ratingElement?.getAttribute('aria-label') ?? ratingElement?.textContent ?? '',
        meta_text: addressElement?.textContent?.trim() ?? '',
      };
    },
    {
      nameSelectors: [...ORG_PAGE_NAME_SELECTORS],
      addressSelector: ORG_PAGE_ADDRESS_SELECTORS,
      ratingSelector: '[class*="business-rating"], [class*="orgpage-header"] [class*="rating"]',
      headerSelector: ORG_PAGE_HEADER_SELECTORS,
    },
  );

  return [
    {
      href,
      link_text: data.link_text,
      card_text: data.card_text,
      rating_aria_label: data.rating_aria_label,
      meta_text: data.meta_text,
    },
  ];
}

/** Collect page-level metadata without interpreting business fields. */
export async function harvestPageMeta(page: Page): Promise<PageMeta> {
  return page.evaluate(
    ({ orgNameSelectors, searchNameSelectors, addressSelector, headerSelector }) => {
      const nameSelectors = [...orgNameSelectors, ...searchNameSelectors];
      let header_text = '';

      for (const selector of nameSelectors) {
        const text = document.querySelector(selector)?.textContent?.trim();

        if (text) {
          header_text = text;
          break;
        }
      }

      if (!header_text) {
        header_text = document.title.replace(/\s*—\s*Яндекс\.?\s*Карты.*/i, '').trim();
      }

      const address_text = document.querySelector(addressSelector)?.textContent?.trim() ?? '';
      const headerBlockText = document.querySelector(headerSelector)?.textContent?.trim() ?? '';

      return {
        title: document.title,
        header_text: header_text || headerBlockText,
        address_text,
      };
    },
    {
      orgNameSelectors: [...ORG_PAGE_NAME_SELECTORS],
      searchNameSelectors: [...SEARCH_SNIPPET_NAME_SELECTORS],
      addressSelector: `${ORG_PAGE_ADDRESS_SELECTORS}, ${ADDRESS_SELECTORS}`,
      headerSelector: ORG_PAGE_HEADER_SELECTORS,
    },
  );
}
