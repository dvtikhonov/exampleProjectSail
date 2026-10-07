import { describe, expect, it } from 'vitest';
import { isAcceptableBrisklyBearerToken } from '../src/tokenCapture/isAcceptableBrisklyBearerToken.js';

describe('isAcceptableBrisklyBearerToken', () => {
  it('accepts JWT-shaped tokens within length bounds', () => {
    expect(
      isAcceptableBrisklyBearerToken('eyJhbGciOiJIUzI1NiJ9.payloadpart.signaturepart'),
    ).toBe(true);
  });

  it('rejects short non-JWT values', () => {
    expect(isAcceptableBrisklyBearerToken('null')).toBe(false);
    expect(isAcceptableBrisklyBearerToken('abcd')).toBe(false);
    expect(isAcceptableBrisklyBearerToken('eyJ.x.y')).toBe(false);
  });

  it('rejects non three-segment tokens even if long enough', () => {
    expect(isAcceptableBrisklyBearerToken('not-a-jwt-but-long-enough')).toBe(false);
    expect(isAcceptableBrisklyBearerToken('one.two')).toBe(false);
  });

  it('rejects JWT whose exp is in the past', () => {
    const payload = Buffer.from(JSON.stringify({ exp: 1_000 }), 'utf8').toString('base64url');
    expect(isAcceptableBrisklyBearerToken(`eyJhbGciOiJIUzI1NiJ9.${payload}.sig`, 2_000)).toBe(false);
  });

  it('accepts JWT whose exp is still valid', () => {
    const payload = Buffer.from(JSON.stringify({ exp: 5_000 }), 'utf8').toString('base64url');
    expect(isAcceptableBrisklyBearerToken(`eyJhbGciOiJIUzI1NiJ9.${payload}.sig`, 2_000)).toBe(true);
  });
});
