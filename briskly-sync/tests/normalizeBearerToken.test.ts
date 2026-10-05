import { describe, expect, it } from 'vitest';
import { normalizeBearerToken } from '../src/tokenCapture/normalizeBearerToken.js';

describe('normalizeBearerToken', () => {
  it.each([
    ['plain jwt', 'eyJhbGciOiJIUzI1NiJ9.payload.sig', 'eyJhbGciOiJIUzI1NiJ9.payload.sig'],
    ['with Bearer', 'Bearer eyJhbGciOiJIUzI1NiJ9.payload.sig', 'eyJhbGciOiJIUzI1NiJ9.payload.sig'],
    ['with bearer lower', 'bearer  eyJhbGciOiJIUzI1NiJ9.payload.sig', 'eyJhbGciOiJIUzI1NiJ9.payload.sig'],
    ['trimmed', "  eyJ.x.y  \n", 'eyJ.x.y'],
    ['empty', '   ', ''],
  ] as const)('%s', (_label, raw, expected) => {
    expect(normalizeBearerToken(raw)).toBe(expected);
  });
});
