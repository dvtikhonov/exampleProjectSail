import type { Page } from 'playwright';
import { config } from '../config.js';
import { createContext, gotoWithJiggle, waitForSelectorWithJiggle } from '../browser.js';
import { humanMouseJiggle } from '../humanMouseJiggle.js';
import { NetworkJsonCollector, pickString, walkJson } from '../utils/jsonExtract.js';
import {
  collectEmbeddedPageState,
  extractReviewsFromPageState,
} from '../utils/pageStateExtract.js';
import {
  buildReviewsUrl,
  normalizeOrgUrl,
} from '../utils/yandexUrl.js';
import {
  extractRatingFields,
  isPlausibleOrgName,
  ORG_PAGE_NAME_SELECTORS,
  recordMatchesOrgId,
} from '../utils/orgExtract.js';
import {
  dedupeReviews,
  dedupeReviewsByContent,
  mapRecordToReview,
  normalizeDomReviews,
} from '../utils/reviewExtract.js';
import type { OrganizationMeta, ParsedReview } from '../types.js';

interface SyncReviewsInput {
  org_id: string;
  canonical_url: string;
}

/**
 * Sync organization metadata and reviews from Yandex Maps reviews page.
 */
export async function syncReviews(input: SyncReviewsInput): Promise<{ org: OrganizationMeta; reviews: ParsedReview[] }> {
  const reviewsUrl = buildReviewsUrl(input.canonical_url);
  const context = await createContext();
  const page = await context.newPage();
  const collector = new NetworkJsonCollector();
  collector.attach(page);

  try {
    await gotoWithJiggle(page, reviewsUrl);
    await waitForSelectorWithJiggle(page, 'body');
    await page.waitForSelector('[class*="business-review-view"], [class*="business-rating"]', {
      timeout: config.navigationTimeoutMs,
      state: 'attached',
    }).catch(() => {});
    await page.waitForTimeout(2000);

    const domMeta = await readOrgMetaFromDom(page, input.org_id, input.canonical_url);
    const apiMeta = extractOrgMetaFromPayloads(collector.getPayloads(), input.org_id, input.canonical_url);
    const org = mergeOrgMeta(domMeta, apiMeta);
    const pageState = await collectEmbeddedPageState(page);
    const pageStateReviews = extractReviewsFromPageState(pageState);

    let reviews = dedupeReviews([
      ...normalizeDomReviews(await extractReviewsFromDom(page)),
      ...pageStateReviews,
    ]);
    const targetCount = org.reviews_count ?? (reviews.length > 0 ? reviews.length : 30);
    let idleIterations = 0;
    let previousCount = reviews.length;

    while (idleIterations < config.syncMaxIdleIterations) {
      if (targetCount > 0 && reviews.length >= targetCount) {
        break;
      }

      const responsePromise = page
        .waitForResponse(
          (response) => {
            const url = response.url().toLowerCase();
            return (
              response.status() === 200 &&
              (url.includes('review') || url.includes('ugc') || url.includes('business'))
            );
          },
          { timeout: config.syncScrollDelayMs },
        )
        .catch(() => null);

      await scrollReviewsPanel(page);

      await responsePromise;
      await page.waitForTimeout(config.syncScrollDelayMs);

      await humanMouseJiggle(page, {
        minPx: config.mouseJiggleMinPx,
        maxPx: config.mouseJiggleMaxPx,
      });

      const fromNetwork = extractReviewsFromAllPayloads(collector.getPayloadsWithMeta());
      const fromDom = normalizeDomReviews(await extractReviewsFromDom(page));
      const fromPageState = extractReviewsFromPageState(await collectEmbeddedPageState(page));
      reviews = dedupeReviews([...reviews, ...fromDom, ...fromPageState, ...fromNetwork]);

      if (reviews.length === previousCount) {
        idleIterations += 1;
      } else {
        idleIterations = 0;
        previousCount = reviews.length;
      }
    }

    const finalReviews = dedupeReviewsByContent(reviews);

    return { org, reviews: finalReviews };
  } finally {
    await page.close();
    await context.close();
  }
}

function mergeOrgMeta(domMeta: OrganizationMeta, apiMeta: OrganizationMeta | null): OrganizationMeta {
  if (!apiMeta) {
    return domMeta;
  }

  const name = isPlausibleOrgName(apiMeta.name)
    ? apiMeta.name
    : isPlausibleOrgName(domMeta.name)
      ? domMeta.name
      : apiMeta.name || domMeta.name;

  return {
    org_id: domMeta.org_id,
    canonical_url: domMeta.canonical_url,
    name,
    address: apiMeta.address || domMeta.address,
    average_rating: apiMeta.average_rating ?? domMeta.average_rating,
    reviews_count: apiMeta.reviews_count ?? domMeta.reviews_count,
    ratings_count: apiMeta.ratings_count ?? domMeta.ratings_count,
  };
}

async function readOrgMetaFromDom(
  page: Page,
  orgId: string,
  canonicalUrl: string,
): Promise<OrganizationMeta> {
  await humanMouseJiggle(page, {
    minPx: config.mouseJiggleMinPx,
    maxPx: config.mouseJiggleMaxPx,
  });

  const data = await page.evaluate((nameSelectors) => {
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
      const titleMatch = title.match(/(?:Отзывы о|Организация)\s+[«"]([^»"]+)[»"]/i);

      if (titleMatch?.[1] && isPlausibleName(titleMatch[1])) {
        name = titleMatch[1];
      } else if (isPlausibleName(title)) {
        name = title;
      }
    }

    const address =
      document.querySelector('[class*="orgpage-header"] [class*="address"], [class*="business-contacts"] [class*="address"]')
        ?.textContent?.trim() ?? '';

    const ratingBlock = document.querySelector(
      '[class*="business-rating-badge-view__rating"], [class*="business-summary-rating-badge-view__rating"], [class*="business-rating"], [class*="orgpage-header"] [class*="rating"]',
    );
    const ratingText = ratingBlock?.textContent?.replace(',', '.') ?? '';
    const ratingMatch = ratingText.match(/(\d+(?:\.\d+)?)/);
    const tabsText =
      document.querySelector('[class*="orgpage-tabs"], [class*="card-actions-view"], [class*="orgpage-header"]')
        ?.textContent ?? '';
    const pageText =
      document.querySelector('[class*="orgpage"], [class*="business-card-view"]')?.textContent ?? tabsText;
    const reviewsMatch = pageText.match(/Отзывы\s*(\d[\d\s]*)/i);
    const ratingsMatch =
      (document.querySelector('[class*="business-rating-amount"], [class*="business-rating-with-text-view"]')?.textContent ?? '')
        .match(/(\d[\d\s]*)\s*оцен/i) ?? tabsText.match(/(\d[\d\s]*)\s*оцен/i);

    return {
      name,
      address,
      average_rating: ratingMatch ? Number.parseFloat(ratingMatch[1]) : null,
      reviews_count: reviewsMatch ? Number.parseInt(reviewsMatch[1].replace(/\s/g, ''), 10) : null,
      ratings_count: ratingsMatch ? Number.parseInt(ratingsMatch[1].replace(/\s/g, ''), 10) : null,
    };
  }, [...ORG_PAGE_NAME_SELECTORS]);

  return {
    org_id: orgId,
    name: data.name,
    address: data.address,
    average_rating:
      data.average_rating === null
        ? null
        : Math.floor(data.average_rating * 10 + 0.0001) / 10,
    reviews_count: data.reviews_count,
    ratings_count: data.ratings_count,
    canonical_url: normalizeOrgUrl(canonicalUrl, orgId),
  };
}

function extractOrgMetaFromPayloads(
  payloads: unknown[],
  orgId: string,
  canonicalUrl: string,
): OrganizationMeta | null {
  let meta: OrganizationMeta | null = null;

  for (const payload of payloads) {
    walkJson(payload, (record) => {
      if (!recordMatchesOrgId(record, orgId)) {
        return;
      }

      const ratingFields = extractRatingFields(record);
      const name = pickString(record, ['shortName', 'name', 'title', 'caption']) ?? '';

      if (!isPlausibleOrgName(name) && meta !== null) {
        return;
      }

      meta = {
        org_id: orgId,
        name,
        address: pickString(record, ['address', 'fullAddress', 'formattedAddress']) ?? '',
        ...ratingFields,
        canonical_url: normalizeOrgUrl(canonicalUrl, orgId),
      };
    });
  }

  return meta;
}

function extractReviewsFromAllPayloads(payloads: Array<{ url: string; json: unknown }>): ParsedReview[] {
  const reviews: ParsedReview[] = [];

  for (const payload of payloads) {
    walkJson(payload.json, (record) => {
      const review = mapRecordToReview(record);

      if (review) {
        reviews.push(review);
      }
    });
  }

  return dedupeReviews(reviews);
}

async function extractReviewsFromDom(page: Page): Promise<ParsedReview[]> {
  await humanMouseJiggle(page, {
    minPx: config.mouseJiggleMinPx,
    maxPx: config.mouseJiggleMaxPx,
  });

  return page.evaluate(() => {
    const results: ParsedReview[] = [];

    const reviewRoot =
      document.querySelector('[class*="business-reviews-page"], [class*="business-card-view__main"]') ??
      document.body;

    let cards = Array.from(reviewRoot.querySelectorAll('[class*="business-review-view"]'));

    if (cards.length === 0) {
      cards = Array.from(reviewRoot.querySelectorAll('[class*="scroll__item"]')).filter((card) => {
        const text = card.textContent?.trim() ?? '';

        return (
          card.querySelector('[class*="business-review"], [class*="review-view"], [class*="review-text"]') !==
            null || text.length >= 80
        );
      });
    }

    cards.forEach((card, index) => {
      const author =
        card.querySelector(
          '[class*="business-review-view__author"], [class*="business-review-view__user"], [class*="review-author"], [class*="author"]',
        )?.textContent?.trim() ?? 'Аноним';
      const text =
        card.querySelector(
          '[class*="business-review-view__body-text"], [class*="business-review-view__text"], [class*="review-text"], [class*="review-view__text"], [class*="body-text"], [class*="text"]',
        )?.textContent?.trim() ?? null;
      const dateText =
        card.querySelector('[class*="business-review-view__date"], [class*="review-date"], [class*="date"], time')
          ?.textContent?.trim() ??
        card.querySelector('time')?.getAttribute('datetime') ??
        null;
      const ratingText =
        card
          .querySelector(
            '[class*="business-rating-badge-view__stars"], [class*="rating"], [aria-label*="Оценка"], [aria-label*="оценка"]',
          )
          ?.getAttribute('aria-label') ?? '';
      const ratingMatch = ratingText.replace(',', '.').match(/(\d+(?:\.\d+)?)/);
      const dataId = card.getAttribute('data-review-id') ?? card.getAttribute('data-id');

      results.push({
        external_id: dataId ?? `dom-${index}-${author}`,
        author_name: author,
        published_at: dateText,
        text,
        rating: ratingMatch ? Number.parseFloat(ratingMatch[1]) : null,
      });
    });

    return results;
  });
}

async function scrollReviewsPanel(page: Page): Promise<void> {
  const panelSelector =
    '[class*="business-reviews-page"] [class*="scroll__container"], [class*="business-reviews-page"] [class*="scroll"], [class*="scroll__container"]';
  const panel = page.locator(panelSelector).first();

  if ((await panel.count()) > 0) {
    const box = await panel.boundingBox();

    if (box) {
      await page.mouse.move(box.x + box.width / 2, box.y + Math.min(box.height / 2, box.height - 10));
    }

    await panel.hover();
    await page.mouse.wheel(0, 1200);

    await panel.evaluate((element) => {
      element.scrollTop += 1200;
      element.dispatchEvent(new Event('scroll', { bubbles: true }));
    });

    return;
  }

  await page.mouse.wheel(0, 1200);
}
