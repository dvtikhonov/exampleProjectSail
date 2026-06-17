import type { Response } from 'playwright';

export interface CollectedPayload {
  url: string;
  json: unknown;
}

/** Collect JSON payloads from Playwright network responses. */
export class NetworkJsonCollector {
  private readonly payloads: CollectedPayload[] = [];

  /** Attach listener to a Playwright page. */
  attach(page: { on(event: 'response', handler: (response: Response) => void): void }): void {
    page.on('response', (response) => {
      void this.handleResponse(response);
    });
  }

  /** Return all collected JSON payloads. */
  getPayloads(): unknown[] {
    return this.payloads.map((entry) => entry.json);
  }

  /** Return payloads with source response URLs. */
  getPayloadsWithMeta(): CollectedPayload[] {
    return [...this.payloads];
  }

  /** Clear collected payloads. */
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

/** Depth-first walk over JSON trees. */
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

/** Pick first non-empty string from candidate keys. */
export function pickString(record: Record<string, unknown>, keys: string[]): string | null {
  for (const key of keys) {
    const value = record[key];
    if (typeof value === 'string' && value.trim() !== '') {
      return value.trim();
    }
  }

  return null;
}

/** Pick nested record by keys. */
export function pickRecord(record: Record<string, unknown>, keys: string[]): Record<string, unknown> | null {
  for (const key of keys) {
    const value = record[key];
    if (value !== null && typeof value === 'object' && !Array.isArray(value)) {
      return value as Record<string, unknown>;
    }
  }

  return null;
}
