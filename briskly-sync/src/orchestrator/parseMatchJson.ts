import type { MatchCandidate, MatchLineResult } from './types.js';

/**
 * Парсит JSON-ответ LLM (OutputContract) и отбрасывает любые price от модели.
 */
export function parseMatchJson(rawText: string): MatchLineResult[] {
  const jsonText = extractJsonPayload(rawText);
  let parsed: unknown;
  try {
    parsed = JSON.parse(jsonText);
  } catch (err) {
    throw new Error(
      `Failed to parse match JSON: ${err instanceof Error ? err.message : String(err)}`,
    );
  }

  const rows = normalizeToArray(parsed);
  const results: MatchLineResult[] = [];

  for (const row of rows) {
    if (!row || typeof row !== 'object') {
      continue;
    }
    const record = row as Record<string, unknown>;
    const lineKey = String(record.line_key ?? '').trim();
    if (!lineKey) {
      continue;
    }

    const candidatesRaw = Array.isArray(record.candidates) ? record.candidates : [];
    const candidates: MatchCandidate[] = [];
    for (const c of candidatesRaw) {
      if (!c || typeof c !== 'object') {
        continue;
      }
      const cand = c as Record<string, unknown>;
      const id = Number(cand.id);
      const name = String(cand.name ?? '').trim();
      if (!Number.isFinite(id) || name === '') {
        continue;
      }
      // Anti-tamper: price от LLM игнорируем полностью.
      candidates.push({ id, name });
    }

    results.push({
      line_key: lineKey,
      display_name: String(record.display_name ?? '').trim(),
      compare_name: String(record.compare_name ?? '').trim(),
      candidates,
    });
  }

  return results;
}

function normalizeToArray(parsed: unknown): unknown[] {
  if (Array.isArray(parsed)) {
    return parsed;
  }
  if (parsed && typeof parsed === 'object') {
    const obj = parsed as Record<string, unknown>;
    if (Array.isArray(obj.matches)) {
      return obj.matches;
    }
    if (Array.isArray(obj.results)) {
      return obj.results;
    }
    if (Array.isArray(obj.items)) {
      return obj.items;
    }
  }
  throw new Error('Match JSON must be an array or { matches|results|items: [] }');
}

function extractJsonPayload(rawText: string): string {
  const trimmed = rawText.trim();
  if (!trimmed) {
    throw new Error('Empty match response');
  }

  const fenced = trimmed.match(/```(?:json)?\s*([\s\S]*?)```/i);
  if (fenced?.[1]) {
    return fenced[1].trim();
  }

  const startArr = trimmed.indexOf('[');
  const startObj = trimmed.indexOf('{');
  let start = -1;
  if (startArr === -1) {
    start = startObj;
  } else if (startObj === -1) {
    start = startArr;
  } else {
    start = Math.min(startArr, startObj);
  }

  if (start === -1) {
    throw new Error('No JSON object/array found in match response');
  }

  return trimmed.slice(start);
}
