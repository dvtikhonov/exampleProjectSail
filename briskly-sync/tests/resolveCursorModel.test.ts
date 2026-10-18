import { describe, expect, it } from 'vitest';
import {
  CURSOR_DEFAULT_AUTO_MODEL_ID,
  CURSOR_ROUTER_MODEL_ID,
  DEFAULT_CURSOR_MODEL_ID,
  normalizeCursorModelId,
  resolveCursorModel,
} from '../src/orchestrator/resolveCursorModel.js';

describe('resolveCursorModel', () => {
  it('по умолчанию default (Auto без Router)', () => {
    expect(resolveCursorModel({ env: {} })).toEqual({
      id: CURSOR_DEFAULT_AUTO_MODEL_ID,
    });
    expect(DEFAULT_CURSOR_MODEL_ID).toBe(CURSOR_DEFAULT_AUTO_MODEL_ID);
  });

  it('alias auto → default', () => {
    expect(normalizeCursorModelId('auto')).toBe(CURSOR_DEFAULT_AUTO_MODEL_ID);
    expect(normalizeCursorModelId('Auto')).toBe(CURSOR_DEFAULT_AUTO_MODEL_ID);
    expect(resolveCursorModel({ modelId: 'auto', env: {} })).toEqual({
      id: CURSOR_DEFAULT_AUTO_MODEL_ID,
    });
  });

  it('auto-smart + CURSOR_OPTIMIZE_FOR для Router', () => {
    expect(
      resolveCursorModel({
        env: {
          CURSOR_MODEL: 'auto-smart',
          CURSOR_OPTIMIZE_FOR: 'cost',
        },
      }),
    ).toEqual({
      id: CURSOR_ROUTER_MODEL_ID,
      params: [{ id: 'optimize_for', value: 'cost' }],
    });
  });

  it('явный optimizeFor важнее env (только Router)', () => {
    expect(
      resolveCursorModel({
        modelId: 'auto-smart',
        optimizeFor: 'intelligence',
        env: { CURSOR_OPTIMIZE_FOR: 'cost' },
      }),
    ).toEqual({
      id: CURSOR_ROUTER_MODEL_ID,
      params: [{ id: 'optimize_for', value: 'intelligence' }],
    });
  });

  it('невалидный optimize_for → balanced', () => {
    expect(
      resolveCursorModel({
        modelId: 'auto-smart',
        optimizeFor: 'turbo',
        env: {},
      }),
    ).toEqual({
      id: CURSOR_ROUTER_MODEL_ID,
      params: [{ id: 'optimize_for', value: 'balanced' }],
    });
  });

  it('фиксированная модель — без params', () => {
    expect(
      resolveCursorModel({
        modelId: 'composer-2.5',
        optimizeFor: 'cost',
        env: {},
      }),
    ).toEqual({ id: 'composer-2.5' });
  });

  it('default игнорирует optimize_for', () => {
    expect(
      resolveCursorModel({
        modelId: 'default',
        optimizeFor: 'cost',
        env: {},
      }),
    ).toEqual({ id: 'default' });
  });
});
