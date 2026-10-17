/**
 * Парсинг JSON-ответа vision OCR графика.
 * Дата живёт на группе дня и копируется на каждое блюдо группы.
 */

export interface ScheduleOcrEntry {
  raw_title: string;
  dates: string[];
}

export interface ScheduleOcrDiscarded {
  raw_title: string;
  reason: string;
  /** Дни с фото (Y-m-d). Пусто, если модель их не вернула. */
  dates: string[];
}

export interface ScheduleOcrParsed {
  entries: ScheduleOcrEntry[];
  discarded: ScheduleOcrDiscarded[];
}

const YMD_RE = /^\d{4}-\d{2}-\d{2}$/;

/**
 * Извлекает entries/discarded из текста ассистента (допускает ```json fences).
 */
export function parseScheduleOcrJson(rawText: string): ScheduleOcrParsed {
  const jsonText = extractJsonObject(rawText);
  let decoded: unknown;
  try {
    decoded = JSON.parse(jsonText);
  } catch {
    throw new Error('Schedule OCR response is not valid JSON');
  }

  if (!decoded || typeof decoded !== 'object' || Array.isArray(decoded)) {
    throw new Error('Schedule OCR JSON must be an object');
  }

  const obj = decoded as Record<string, unknown>;
  const grouped = parseDayGroups(obj);
  if (grouped !== null) {
    return grouped;
  }

  const entriesRaw = Array.isArray(obj.entries) ? obj.entries : [];
  const discardedRaw = Array.isArray(obj.discarded) ? obj.discarded : [];

  const entries: ScheduleOcrEntry[] = [];
  for (const row of entriesRaw) {
    if (!row || typeof row !== 'object' || Array.isArray(row)) {
      continue;
    }
    const r = row as Record<string, unknown>;
    const rawTitle = asNonEmptyString(r.raw_title ?? r.name);
    if (!rawTitle) {
      continue;
    }
    const dates = normalizeDates(r.dates);
    if (dates.length === 0) {
      continue;
    }
    entries.push({ raw_title: rawTitle, dates });
  }

  const discarded: ScheduleOcrDiscarded[] = [];
  for (const row of discardedRaw) {
    if (!row || typeof row !== 'object' || Array.isArray(row)) {
      continue;
    }
    const r = row as Record<string, unknown>;
    const rawTitle = asNonEmptyString(r.raw_title) ?? '';
    const reason = asNonEmptyString(r.reason) ?? 'unspecified';
    if (rawTitle === '') {
      continue;
    }
    discarded.push({
      raw_title: rawTitle,
      reason,
      dates: normalizeDates(r.dates),
    });
  }

  return { entries, discarded };
}

/**
 * Группа дня: одна date заголовка на все entries и discarded этой группы.
 * null — в ответе нет groups, разбирать плоский формат.
 */
function parseDayGroups(obj: Record<string, unknown>): ScheduleOcrParsed | null {
  if (!Array.isArray(obj.groups) || obj.groups.length === 0) {
    return null;
  }

  const entries: ScheduleOcrEntry[] = [];
  const discarded: ScheduleOcrDiscarded[] = [];

  for (const group of obj.groups) {
    if (!group || typeof group !== 'object' || Array.isArray(group)) {
      continue;
    }
    const g = group as Record<string, unknown>;
    const headerDates = groupHeaderDates(g);
    const headerDate = headerDates.length === 1 ? headerDates[0] : null;
    const entriesRaw = Array.isArray(g.entries) ? g.entries : [];
    const discardedRaw = Array.isArray(g.discarded) ? g.discarded : [];

    for (const row of entriesRaw) {
      if (!row || typeof row !== 'object' || Array.isArray(row)) {
        continue;
      }
      const r = row as Record<string, unknown>;
      const rawTitle = asNonEmptyString(r.raw_title ?? r.name);
      if (!rawTitle) {
        continue;
      }
      const dates = headerDate !== null ? [headerDate] : normalizeDates(r.dates);
      if (dates.length === 0) {
        continue;
      }
      entries.push({ raw_title: rawTitle, dates });
    }

    for (const row of discardedRaw) {
      if (!row || typeof row !== 'object' || Array.isArray(row)) {
        continue;
      }
      const r = row as Record<string, unknown>;
      const rawTitle = asNonEmptyString(r.raw_title) ?? '';
      const reason = asNonEmptyString(r.reason) ?? 'unspecified';
      if (rawTitle === '') {
        continue;
      }
      const dates = headerDate !== null ? [headerDate] : normalizeDates(r.dates);
      discarded.push({ raw_title: rawTitle, reason, dates });
    }
  }

  return { entries, discarded };
}

/** Одна дата заголовка группы. Несколько дат на группе не размазываются на блюда. */
function groupHeaderDates(group: Record<string, unknown>): string[] {
  if (typeof group.date === 'string') {
    return normalizeDates([group.date]);
  }
  return normalizeDates(group.dates);
}

function normalizeDates(value: unknown): string[] {
  if (!Array.isArray(value)) {
    return [];
  }
  const out: string[] = [];
  for (const item of value) {
    if (typeof item !== 'string') {
      continue;
    }
    const trimmed = item.trim();
    if (YMD_RE.test(trimmed)) {
      out.push(trimmed);
    }
  }
  return [...new Set(out)];
}

function extractJsonObject(rawText: string): string {
  const trimmed = rawText.trim();
  const fence = /```(?:json)?\s*([\s\S]*?)```/i.exec(trimmed);
  const candidate = (fence?.[1] ?? trimmed).trim();
  const start = candidate.indexOf('{');
  const end = candidate.lastIndexOf('}');
  if (start < 0 || end <= start) {
    throw new Error('Schedule OCR response has no JSON object');
  }
  return candidate.slice(start, end + 1);
}

function asNonEmptyString(value: unknown): string | undefined {
  if (typeof value !== 'string') {
    return undefined;
  }
  const trimmed = value.trim();
  return trimmed !== '' ? trimmed : undefined;
}
