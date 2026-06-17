import { describe, expect, it } from 'vitest';
import {
  calculateJiggleDelta,
  isAxisAligned,
  type RandomSource,
} from '../src/humanMouseJiggle.js';

/** Deterministic random source for reproducible tests. */
class SequenceRandomSource implements RandomSource {
  private index = 0;

  constructor(private readonly values: number[]) {}

  next(): number {
    const value = this.values[this.index % this.values.length];
    this.index += 1;
    return value;
  }
}

describe('calculateJiggleDelta', () => {
  it('returns distance >= 10 px for configured minimum', () => {
    for (let i = 0; i < 200; i += 1) {
      const delta = calculateJiggleDelta(10, 80);
      expect(delta.distance).toBeGreaterThanOrEqual(10);
    }
  });

  it('respects custom min/max bounds', () => {
    const random = new SequenceRandomSource([0, 0, 0.5, 0.25]);
    const delta = calculateJiggleDelta(20, 40, random);
    expect(delta.distance).toBeGreaterThanOrEqual(20);
    expect(delta.distance).toBeLessThanOrEqual(40);
  });

  it('produces non axis-aligned deltas over many random samples', () => {
    let nonAxisAligned = 0;

    for (let i = 0; i < 500; i += 1) {
      const delta = calculateJiggleDelta(10, 80);
      if (!isAxisAligned(delta) && delta.dx !== 0 && delta.dy !== 0) {
        nonAxisAligned += 1;
      }
    }

    expect(nonAxisAligned).toBeGreaterThan(400);
  });

  it('uses arbitrary direction via angle (both dx and dy can be non-zero)', () => {
    const random = new SequenceRandomSource([0.5, 0.125]);
    const delta = calculateJiggleDelta(30, 30, random);

    expect(delta.dx).not.toBe(0);
    expect(delta.dy).not.toBe(0);
    expect(isAxisAligned(delta)).toBe(false);
  });
});
