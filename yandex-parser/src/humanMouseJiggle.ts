/**
 * Имитация движения мыши перед действиями на странице.
 * Вызывается перед goto, ожиданием селектора, скроллом, кликом и сбором DOM.
 */
import type { Page } from 'playwright';

/** Смещение курсора за один шаг jiggle. */
export interface JiggleDelta {
  dx: number;
  dy: number;
  distance: number;
}

/** Текущие координаты курсора в viewport (1280×720). */
export interface MousePosition {
  x: number;
  y: number;
}

/** Источник случайных чисел [0, 1) — подменяется в тестах. */
export interface RandomSource {
  next(): number;
}

/** Псевдослучайный источник на Math.random(). */
export class MathRandomSource implements RandomSource {
  next(): number {
    return Math.random();
  }
}

/**
 * Случайный вектор смещения: расстояние ∈ [minPx, maxPx], произвольный угол.
 * При округлении до целых пикселей дистанция может «схлопнуться» — тогда масштабируем.
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

  // Округление cos/sin может дать actualDistance < safeMin — дотягиваем по одной оси.
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

/** Целое в [min, max] включительно. */
function randomInt(min: number, max: number, random: RandomSource): number {
  return min + Math.floor(random.next() * (max - min + 1));
}

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

/** Позиция курсора на странице — WeakMap, чтобы не протекала между контекстами. */
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
  /** Число промежуточных шагов между началом и концом движения (1–2 по умолчанию). */
  intermediateSteps?: number;
  random?: RandomSource;
}

/**
 * Выполнить 1–2 случайных перемещения курсора с паузами, обновить трекинг позиции.
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

/** Сбросить отслеживаемую позицию курсора (для тестов). */
export function resetMousePosition(page: Page, position: MousePosition = { x: 640, y: 360 }): void {
  pageMousePositions.set(page, position);
}

/** true, если смещение строго по одной оси (dx=0 или dy=0). */
export function isAxisAligned(delta: JiggleDelta): boolean {
  return delta.dx === 0 || delta.dy === 0;
}
