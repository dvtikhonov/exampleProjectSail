import type { Page } from 'playwright';

export interface JiggleDelta {
  dx: number;
  dy: number;
  distance: number;
}

export interface MousePosition {
  x: number;
  y: number;
}

export interface RandomSource {
  next(): number;
}

/** Default pseudo-random source (0..1). */
export class MathRandomSource implements RandomSource {
  next(): number {
    return Math.random();
  }
}

/**
 * Compute a random mouse jiggle delta with distance >= minPx and arbitrary direction.
 * Exported for unit testing without Playwright.
 */
export function calculateJiggleDelta(
  minPx: number,
  maxPx: number,
  random: RandomSource = new MathRandomSource(),
): JiggleDelta {
  const safeMin = Math.max(10, minPx);
  const safeMax = Math.max(safeMin, maxPx);
  const span = safeMax - safeMin + 1;
  const distance = safeMin + Math.floor(random.next() * span);
  const angleRadians = random.next() * Math.PI * 2;
  const dx = Math.round(Math.cos(angleRadians) * distance);
  const dy = Math.round(Math.sin(angleRadians) * distance);

  const actualDistance = Math.hypot(dx, dy);

  if (actualDistance >= safeMin) {
    return { dx, dy, distance: actualDistance };
  }

  const scale = safeMin / (actualDistance || 1);
  let scaledDx = Math.round(dx * scale);
  let scaledDy = Math.round(dy * scale);

  if (scaledDx === 0 && scaledDy === 0) {
    scaledDx = safeMin;
  }

  let scaledDistance = Math.hypot(scaledDx, scaledDy);
  while (scaledDistance < safeMin) {
    if (Math.abs(scaledDx) >= Math.abs(scaledDy)) {
      scaledDx += scaledDx >= 0 ? 1 : -1;
    } else {
      scaledDy += scaledDy >= 0 ? 1 : -1;
    }
    scaledDistance = Math.hypot(scaledDx, scaledDy);
  }

  return {
    dx: scaledDx,
    dy: scaledDy,
    distance: scaledDistance,
  };
}

function randomInt(min: number, max: number, random: RandomSource): number {
  return min + Math.floor(random.next() * (max - min + 1));
}

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

const pageMousePositions = new WeakMap<Page, MousePosition>();

function getMousePosition(page: Page): MousePosition {
  const existing = pageMousePositions.get(page);
  if (existing) {
    return existing;
  }

  const initial: MousePosition = { x: 640, y: 360 };
  pageMousePositions.set(page, initial);
  return initial;
}

function setMousePosition(page: Page, position: MousePosition): void {
  pageMousePositions.set(page, position);
}

export interface HumanMouseJiggleOptions {
  minPx?: number;
  maxPx?: number;
  intermediateSteps?: number;
  random?: RandomSource;
}

/**
 * Simulate human-like mouse movement before each page action.
 * Must be called before goto, selector wait, scroll, click, and DOM collection.
 */
export async function humanMouseJiggle(
  page: Page,
  options: HumanMouseJiggleOptions = {},
): Promise<void> {
  const minPx = options.minPx ?? 10;
  const maxPx = options.maxPx ?? 80;
  const random = options.random ?? new MathRandomSource();
  const intermediateSteps = options.intermediateSteps ?? randomInt(1, 2, random);

  const current = getMousePosition(page);
  let x = current.x;
  let y = current.y;

  for (let step = 0; step <= intermediateSteps; step += 1) {
    const { dx, dy } = calculateJiggleDelta(minPx, maxPx, random);
    x += dx;
    y += dy;

    x = Math.max(0, Math.min(1280, x));
    y = Math.max(0, Math.min(720, y));

    await page.mouse.move(x, y);
    await sleep(randomInt(20, 60, random));
  }

  setMousePosition(page, { x, y });
  await sleep(randomInt(50, 150, random));
}

/** Reset tracked mouse position (useful in tests). */
export function resetMousePosition(page: Page, position: MousePosition = { x: 640, y: 360 }): void {
  pageMousePositions.set(page, position);
}

/** Check whether delta is strictly axis-aligned (horizontal or vertical only). */
export function isAxisAligned(delta: JiggleDelta): boolean {
  return delta.dx === 0 || delta.dy === 0;
}
