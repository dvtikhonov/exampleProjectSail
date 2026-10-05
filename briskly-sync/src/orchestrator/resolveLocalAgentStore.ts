import { mkdirSync } from 'node:fs';
import path from 'node:path';
import { JsonlLocalAgentStore, type LocalAgentStore } from '@cursor/sdk';

/**
 * Default Cursor local store needs node:sqlite (Node >= 22.13).
 * On older runtimes fall back to portable JsonlLocalAgentStore.
 */
export async function resolveLocalAgentStore(cwd: string): Promise<LocalAgentStore | undefined> {
  try {
    await import('node:sqlite');
    return undefined;
  } catch {
    const rootDir = path.join(cwd, '.cursor-agent-store');
    mkdirSync(rootDir, { recursive: true });
    return new JsonlLocalAgentStore(rootDir);
  }
}
