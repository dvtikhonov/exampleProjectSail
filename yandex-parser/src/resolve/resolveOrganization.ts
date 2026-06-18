import type { Page } from 'playwright';
import { humanMouseJiggle } from '../humanMouseJiggle.js';
import { config } from '../config.js';
import { NetworkJsonCollector } from '../utils/jsonExtract.js';
import { extractOrgIdFromUrl, isDirectOrgUrl } from '../utils/yandexUrl.js';
import type { ResolveCollectResponseBody } from '../types.js';
import { createContext, gotoWithJiggle, scrollWithJiggle, waitForSelectorWithJiggle } from '../browser.js';
import {
  harvestDirectOrgDom,
  harvestDomFromSearchPage,
  harvestPageMeta,
} from './domHarvest.js';

interface ResolveOptions {
  candidateLimit?: number;
}

/**
 * Navigate Yandex Maps and collect raw network/DOM payloads for organization resolution.
 * Candidate interpretation happens in service-d (Laravel).
 */
export async function resolveOrganization(
  inputUrl: string,
  options: ResolveOptions = {},
): Promise<ResolveCollectResponseBody> {
  const candidateLimit = options.candidateLimit ?? config.resolveCandidateLimit;
  const context = await createContext();
  const page = await context.newPage();
  const collector = new NetworkJsonCollector();
  collector.attach(page);

  try {
    await gotoWithJiggle(page, inputUrl);
    await page.waitForTimeout(1500);

    const resolvedUrl = page.url();
    const directOrgId = extractOrgIdFromUrl(resolvedUrl);
    const isDirectOrg = directOrgId !== null && isDirectOrgUrl(resolvedUrl);

    if (isDirectOrg) {
      await humanMouseJiggle(page, {
        minPx: config.mouseJiggleMinPx,
        maxPx: config.mouseJiggleMaxPx,
      });

      return {
        resolved_url: resolvedUrl,
        is_direct_org: true,
        direct_org_id: directOrgId,
        network_payloads: collector.getPayloads(),
        dom_harvest: await harvestDirectOrgDom(page, resolvedUrl),
        page_meta: await harvestPageMeta(page),
      };
    }

    await waitForSelectorWithJiggle(page, 'body');
    await collectSearchResultsFromScroll(page, candidateLimit);

    await humanMouseJiggle(page, {
      minPx: config.mouseJiggleMinPx,
      maxPx: config.mouseJiggleMaxPx,
    });

    return {
      resolved_url: resolvedUrl,
      is_direct_org: false,
      direct_org_id: null,
      network_payloads: collector.getPayloads(),
      dom_harvest: await harvestDomFromSearchPage(page),
      page_meta: await harvestPageMeta(page),
    };
  } finally {
    await page.close();
    await context.close();
  }
}

async function collectSearchResultsFromScroll(page: Page, candidateLimit: number): Promise<void> {
  const maxScrolls = Math.ceil(candidateLimit / 5);

  for (let i = 0; i < maxScrolls; i += 1) {
    await scrollWithJiggle(page, 900);
    await page.waitForTimeout(config.syncScrollDelayMs);
  }
}
