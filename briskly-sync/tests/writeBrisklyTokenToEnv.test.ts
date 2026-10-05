import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';
import { writeBrisklyTokenToEnv } from '../src/cli/writeBrisklyTokenToEnv.js';

describe('writeBrisklyTokenToEnv', () => {
  it('creates .env when missing', () => {
    const dir = mkdtempSync(join(tmpdir(), 'briskly-env-'));
    const envPath = join(dir, '.env');
    writeBrisklyTokenToEnv(envPath, 'eyJ.abc.def');
    expect(readFileSync(envPath, 'utf8')).toBe('BRISKLY_TOKEN=eyJ.abc.def\n');
  });

  it('replaces existing BRISKLY_TOKEN and keeps other keys', () => {
    const dir = mkdtempSync(join(tmpdir(), 'briskly-env-'));
    const envPath = join(dir, '.env');
    writeFileSync(envPath, 'FOO=1\nBRISKLY_TOKEN=old\nBAR=2\n', 'utf8');
    writeBrisklyTokenToEnv(envPath, 'new-token');
    expect(readFileSync(envPath, 'utf8')).toBe('FOO=1\nBRISKLY_TOKEN=new-token\nBAR=2\n');
  });

  it('appends BRISKLY_TOKEN when absent', () => {
    const dir = mkdtempSync(join(tmpdir(), 'briskly-env-'));
    const envPath = join(dir, '.env');
    writeFileSync(envPath, 'FOO=1\n', 'utf8');
    writeBrisklyTokenToEnv(envPath, 'tok');
    expect(readFileSync(envPath, 'utf8')).toBe('FOO=1\nBRISKLY_TOKEN=tok\n');
  });
});
