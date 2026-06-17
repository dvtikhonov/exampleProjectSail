import { chromium, type Browser, type BrowserContext, type Page } from 'playwright';
import { config } from './config.js';
import { humanMouseJiggle } from './humanMouseJiggle.js';

let browserInstance: Browser | null = null;

/** Lazily launch a shared Chromium browser instance. */
export async function getBrowser(): Promise<Browser> {
  if (browserInstance && browserInstance.isConnected()) {
    return browserInstance;
  }

  browserInstance = await chromium.launch({
    headless: config.headless,
    args: [
      '--disable-blink-features=AutomationControlled',
      '--no-sandbox',
      '--disable-setuid-sandbox',
    ],
  });

  return browserInstance;
}

/** Create an isolated browser context with Russian locale. */
export async function createContext(): Promise<BrowserContext> {
  const browser = await getBrowser();

  return browser.newContext({
    locale: 'ru-RU',
    timezoneId: 'Europe/Moscow',
    viewport: { width: 1280, height: 720 },
    userAgent:
      'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
  });
}

export interface PageActionOptions {
  minPx?: number;
  maxPx?: number;
}

/** Navigate with humanMouseJiggle before the action. */
export async function gotoWithJiggle(
  page: Page,
  url: string,
  options: PageActionOptions = {},
): Promise<void> {
  await humanMouseJiggle(page, {
    minPx: options.minPx ?? config.mouseJiggleMinPx,
    maxPx: options.maxPx ?? config.mouseJiggleMaxPx,
  });

  await page.goto(url, {
    waitUntil: 'domcontentloaded',
    timeout: config.navigationTimeoutMs,
  });
}

/** Wait for selector with jiggle beforehand. */
export async function waitForSelectorWithJiggle(
  page: Page,
  selector: string,
  options: PageActionOptions = {},
): Promise<void> {
  await humanMouseJiggle(page, {
    minPx: options.minPx ?? config.mouseJiggleMinPx,
    maxPx: options.maxPx ?? config.mouseJiggleMaxPx,
  });

  await page.waitForSelector(selector, {
    timeout: config.navigationTimeoutMs,
    state: 'attached',
  });
}

/** Scroll page/container with jiggle beforehand. */
export async function scrollWithJiggle(
  page: Page,
  deltaY: number,
  options: PageActionOptions = {},
): Promise<void> {
  await humanMouseJiggle(page, {
    minPx: options.minPx ?? config.mouseJiggleMinPx,
    maxPx: options.maxPx ?? config.mouseJiggleMaxPx,
  });

  await page.mouse.wheel(0, deltaY);
}

/** Gracefully close the shared browser (for shutdown). */
export async function closeBrowser(): Promise<void> {
  if (browserInstance) {
    await browserInstance.close();
    browserInstance = null;
  }
}
