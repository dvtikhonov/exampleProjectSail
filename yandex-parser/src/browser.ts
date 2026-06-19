/**
 * Обёртка над Playwright: общий Chromium, контекст с ru-RU и действия с «дрожанием» курсора.
 * Jiggle снижает вероятность детекта автоматизации перед goto/scroll/wait.
 */
import { chromium, type Browser, type BrowserContext, type Page } from 'playwright';
import { config } from './config.js';
import { humanMouseJiggle } from './humanMouseJiggle.js';

let browserInstance: Browser | null = null;

/** Ленивый запуск общего экземпляра Chromium. */
export async function getBrowser(): Promise<Browser> {
  if (browserInstance && browserInstance.isConnected()) {
    return browserInstance;
  }

  browserInstance = await chromium.launch({
    headless: config.headless,
    // Флаги для headless в Docker и маскировки webdriver.
    args: [
      '--disable-blink-features=AutomationControlled',
      '--no-sandbox',
      '--disable-setuid-sandbox',
    ],
  });

  return browserInstance;
}

/** Изолированный контекст с локалью ru-RU и типичным desktop user-agent. */
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
  /** Переопределить минимальный шаг jiggle для одного действия. */
  minPx?: number;
  /** Переопределить максимальный шаг jiggle для одного действия. */
  maxPx?: number;
}

/** Переход по URL после jiggle; waitUntil: domcontentloaded. */
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

/** Ожидание селектора (state: attached) после jiggle. */
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

/** Скролл колёсиком после jiggle. */
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

/** Закрыть общий браузер при shutdown процесса. */
export async function closeBrowser(): Promise<void> {
  if (browserInstance) {
    await browserInstance.close();
    browserInstance = null;
  }
}
