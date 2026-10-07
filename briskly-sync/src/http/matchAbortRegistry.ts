/**
 * In-memory abort flags: generation → «не слать success-колбэк».
 * SDK cancel не вызываем — агент может доработать; глушим только callback.
 */

export interface MatchAbortEntry {
  aborted: boolean;
}

export class MatchAbortRegistry {
  private readonly entries = new Map<string, MatchAbortEntry>();

  static key(sessionId: string, matchGeneration: string): string {
    return `${sessionId}:${matchGeneration}`;
  }

  register(sessionId: string, matchGeneration: string): MatchAbortEntry {
    const key = MatchAbortRegistry.key(sessionId, matchGeneration);
    const entry: MatchAbortEntry = { aborted: false };
    this.entries.set(key, entry);
    return entry;
  }

  /**
   * Помечает generation aborted. Возвращает true, если запись была.
   */
  abort(sessionId: string, matchGeneration: string): boolean {
    const key = MatchAbortRegistry.key(sessionId, matchGeneration);
    const entry = this.entries.get(key);
    if (!entry) {
      return false;
    }
    entry.aborted = true;
    return true;
  }

  get(sessionId: string, matchGeneration: string): MatchAbortEntry | undefined {
    return this.entries.get(MatchAbortRegistry.key(sessionId, matchGeneration));
  }

  delete(sessionId: string, matchGeneration: string): void {
    this.entries.delete(MatchAbortRegistry.key(sessionId, matchGeneration));
  }

  clear(): void {
    this.entries.clear();
  }
}

/** Singleton для sidecar-процесса. */
export const matchAbortRegistry = new MatchAbortRegistry();
