import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';
import {
  assertPhpPromptDto,
  composeAgentPrompt,
  looksLikeEnumMatchNamesSystem,
} from '../src/orchestrator/buildMatchPrompt.js';
import type { ComboCatalogPromptDto } from '../src/orchestrator/types.js';

const fixturesDir = join(dirname(fileURLToPath(import.meta.url)), '..', 'fixtures');

describe('buildMatchPrompt', () => {
  it('composeAgentPrompt использует system/user из PHP DTO без локального ENUM', () => {
    const prompt = JSON.parse(
      readFileSync(join(fixturesDir, 'prompt-from-php.json'), 'utf8'),
    ) as ComboCatalogPromptDto;

    expect(looksLikeEnumMatchNamesSystem(prompt.system)).toBe(true);
    expect(prompt.user).toContain('120 грамм');
    // weight_label канонизирован; сырой «120г» как отдельный label не должен остаться в JSON weight_label
    expect(prompt.user).toContain('"weight_label":"120 грамм"');
    expect(prompt.user).not.toContain('"weight_label":"120г"');

    const composed = composeAgentPrompt(prompt);
    expect(composed.startsWith(prompt.system.trim())).toBe(true);
    expect(composed).toContain(prompt.user.trim());
    expect(composed).toContain('---');
  });

  it('отклоняет пустой system (запрет локальной заглушки)', () => {
    expect(() => assertPhpPromptDto({ system: '  ', user: 'x' })).toThrow(/system/);
    expect(() => assertPhpPromptDto({ system: 'ok', user: '' })).toThrow(/user/);
  });
});
