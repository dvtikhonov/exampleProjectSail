import {
  LLM_PING_EXPECTED,
  LLM_PING_PROMPT,
  LLM_PING_TIMEOUT_MS,
  isLlmPingOk,
  normalizeLlmPingReply,
} from './pingLlmReply.js';
import {
  awaitCursorRun,
  disposeCursorAgent,
  startCursorRun,
} from './runMatch.js';
import type { MatchRunInput } from './types.js';
import { withTimeout } from './withTimeout.js';

export {
  LLM_PING_EXPECTED,
  LLM_PING_PROMPT,
  LLM_PING_TIMEOUT_MS,
  isLlmPingOk,
  normalizeLlmPingReply,
} from './pingLlmReply.js';

export interface PingLlmOptions {
  cursorApiKey?: string;
  modelId?: string;
  cwd?: string;
  /** Общий лимит create+send+wait, мс. */
  timeoutMs?: number;
}

/**
 * Live smoke: Agent.create + send(PING) + wait; успех только при ответе PONG.
 */
export async function pingLlm(options: PingLlmOptions = {}): Promise<string> {
  const apiKey = (options.cursorApiKey ?? process.env.CURSOR_API_KEY ?? '').trim();
  if (!apiKey) {
    throw new Error('CURSOR_API_KEY is required for LLM ping');
  }

  const timeoutMs = options.timeoutMs ?? LLM_PING_TIMEOUT_MS;
  const deadline = Date.now() + timeoutMs;
  const input: MatchRunInput = {
    prompt: { system: 'ping', user: LLM_PING_PROMPT },
    sourceLines: [],
    brisklySnapshot: [],
    cursorApiKey: apiKey,
    modelId: options.modelId,
    cwd: options.cwd,
    enableMcp: false,
  };

  const started = await withTimeout(
    startCursorRun(LLM_PING_PROMPT, input),
    timeoutMs,
    'llm ping handshake',
  );

  try {
    const remainingMs = Math.max(1_000, deadline - Date.now());
    const reply = await withTimeout(
      awaitCursorRun(started.run),
      remainingMs,
      'llm ping wait',
    );
    if (!isLlmPingOk(reply)) {
      throw new Error(
        `LLM ping: expected ${LLM_PING_EXPECTED}, got ${JSON.stringify(normalizeLlmPingReply(reply))}`,
      );
    }
    return normalizeLlmPingReply(reply).toUpperCase();
  } finally {
    await disposeCursorAgent(started.agent);
  }
}
