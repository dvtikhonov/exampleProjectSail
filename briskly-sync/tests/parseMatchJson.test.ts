import { describe, expect, it } from 'vitest';
import { parseMatchJson } from '../src/orchestrator/parseMatchJson.js';

describe('parseMatchJson', () => {
  it('парсит JSON и отбрасывает price от LLM', () => {
    const raw = JSON.stringify([
      {
        line_key: 'single:1',
        display_name: 'Суп',
        compare_name: 'суп',
        candidates: [{ id: 10, name: 'Суп', price: 999 }],
        price: '1.00',
      },
    ]);

    const lines = parseMatchJson(raw);
    expect(lines).toHaveLength(1);
    expect(lines[0]?.candidates).toEqual([{ id: 10, name: 'Суп' }]);
    expect(lines[0]).not.toHaveProperty('price');
    expect(JSON.stringify(lines[0]?.candidates[0])).not.toContain('price');
  });

  it('достаёт JSON из markdown fence', () => {
    const raw = 'Вот результат:\n```json\n[{"line_key":"a","display_name":"A","compare_name":"a","candidates":[]}]\n```\n';
    const lines = parseMatchJson(raw);
    expect(lines[0]?.line_key).toBe('a');
  });
});
