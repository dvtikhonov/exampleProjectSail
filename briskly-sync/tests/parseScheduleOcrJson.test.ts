import { describe, expect, it } from 'vitest';
import { parseScheduleOcrJson } from '../src/scheduleOcr/parseScheduleOcrJson.js';

describe('parseScheduleOcrJson', () => {
  it('парсит чистый JSON', () => {
    const parsed = parseScheduleOcrJson(
      JSON.stringify({
        entries: [{ raw_title: 'Картофельное пюре', dates: ['2026-08-13', '2026-08-14'] }],
        discarded: [{ raw_title: 'хз', reason: 'dish_not_found' }],
      }),
    );
    expect(parsed.entries).toEqual([
      { raw_title: 'Картофельное пюре', dates: ['2026-08-13', '2026-08-14'] },
    ]);
    expect(parsed.discarded).toEqual([{ raw_title: 'хз', reason: 'dish_not_found', dates: [] }]);
  });

  it('сохраняет даты discarded — только дни с фото', () => {
    const parsed = parseScheduleOcrJson(
      JSON.stringify({
        entries: [],
        discarded: [{
          raw_title: 'Ветчинный',
          reason: 'dish_not_found',
          dates: ['2026-08-15', '2026-08-15', 'нет'],
        }],
      }),
    );
    expect(parsed.discarded).toEqual([{
      raw_title: 'Ветчинный',
      reason: 'dish_not_found',
      dates: ['2026-08-15'],
    }]);
  });

  it('принимает name как алиас raw_title и fences', () => {
    const parsed = parseScheduleOcrJson(`\`\`\`json
{"entries":[{"name":"Суп","dates":["2026-08-13"]}],"discarded":[]}
\`\`\``);
    expect(parsed.entries[0]?.raw_title).toBe('Суп');
  });

  it('дата заголовка группы копируется на entries и discarded', () => {
    const parsed = parseScheduleOcrJson(JSON.stringify({
      groups: [
        {
          date: '2026-10-26',
          entries: [{ raw_title: 'Салат «Обжорка»' }],
          discarded: [{ raw_title: 'Смак', reason: 'dish_not_found' }],
        },
        {
          date: '2026-10-27',
          entries: [{ name: 'Салат «Греческий»' }],
          discarded: [],
        },
      ],
    }));
    expect(parsed.entries).toEqual([
      { raw_title: 'Салат «Обжорка»', dates: ['2026-10-26'] },
      { raw_title: 'Салат «Греческий»', dates: ['2026-10-27'] },
    ]);
    expect(parsed.discarded).toEqual([
      { raw_title: 'Смак', reason: 'dish_not_found', dates: ['2026-10-26'] },
    ]);
  });

  it('несколько дат на группе не размазываются на блюда', () => {
    const parsed = parseScheduleOcrJson(JSON.stringify({
      groups: [{
        dates: ['2026-10-26', '2026-10-27', '2026-10-28', '2026-10-29', '2026-10-30', '2026-10-31', '2026-11-01'],
        entries: [],
        discarded: [{ raw_title: 'Смак', reason: 'dish_not_found', dates: ['2026-10-28'] }],
      }],
    }));
    expect(parsed.discarded).toEqual([
      { raw_title: 'Смак', reason: 'dish_not_found', dates: ['2026-10-28'] },
    ]);
  });

  it('пустые entries допустимы', () => {
    const parsed = parseScheduleOcrJson('{"entries":[],"discarded":[]}');
    expect(parsed.entries).toEqual([]);
  });
});
