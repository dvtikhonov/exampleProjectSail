/**
 * Утилиты для работы с JSON: перехват сети Playwright и обход вложенных деревьев.
 */
import type { Response } from 'playwright';

export interface CollectedPayload {
  /** URL ответа, из которого извлечён JSON. */
  url: string;
  json: unknown;
}

/** Слушает response и копит JSON с релевантных URL Яндекс.Карт. */
export class NetworkJsonCollector {
  private readonly payloads: CollectedPayload[] = [];

  /** Подписаться на response страницы. */
  attach(page: { on(event: 'response', handler: (response: Response) => void): void }): void {
    page.on('response', (response) => {
      void this.handleResponse(response);
    });
  }

  /** Только JSON без метаданных URL. */
  getPayloads(): unknown[] {
    return this.payloads.map((entry) => entry.json);
  }

  /** Копия payload с URL источника. */
  getPayloadsWithMeta(): CollectedPayload[] {
    return [...this.payloads];
  }

  /** Очистить накопленные ответы. */
  clear(): void {
    this.payloads.length = 0;
  }

  private async handleResponse(response: Response): Promise<void> {
    const contentType = response.headers()['content-type'] ?? '';
    if (!contentType.includes('json') && !contentType.includes('javascript')) {
      return;
    }

    const url = response.url();
    if (!this.isRelevantUrl(url)) {
      return;
    }

    try {
      const json = await response.json();
      this.payloads.push({ url, json });
    } catch {
      // Ignore non-JSON bodies.
    }
  }

  private isRelevantUrl(url: string): boolean {
    const lower = url.toLowerCase();
    // Отсекаем тайлы, метрику и прочий шум — оставляем maps/search/review/business/org.
    return (
      lower.includes('yandex') &&
      (lower.includes('maps') ||
        lower.includes('search') ||
        lower.includes('review') ||
        lower.includes('business') ||
        lower.includes('org'))
    );
  }
}

/** Обход JSON в глубину: visitor вызывается для каждого объекта с путём ключей. */
export function walkJson(
  node: unknown,
  visitor: (value: Record<string, unknown>, path: string[]) => void,
  path: string[] = [],
): void {
  if (Array.isArray(node)) {
    node.forEach((item, index) => walkJson(item, visitor, [...path, String(index)]));
    return;
  }

  if (node !== null && typeof node === 'object') {
    const record = node as Record<string, unknown>;
    visitor(record, path);
    Object.entries(record).forEach(([key, value]) => walkJson(value, visitor, [...path, key]));
  }
}

/** Первое непустое строковое поле из списка ключей. */
export function pickString(record: Record<string, unknown>, keys: string[]): string | null {
  for (const key of keys) {
    const value = record[key];
    if (typeof value === 'string' && value.trim() !== '') {
      return value.trim();
    }
  }

  return null;
}

/** Первый вложенный объект (не массив) из списка ключей. */
export function pickRecord(record: Record<string, unknown>, keys: string[]): Record<string, unknown> | null {
  for (const key of keys) {
    const value = record[key];
    if (value !== null && typeof value === 'object' && !Array.isArray(value)) {
      return value as Record<string, unknown>;
    }
  }

  return null;
}
