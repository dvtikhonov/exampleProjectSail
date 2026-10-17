/**
 * Cursor vision OCR: Agent.create + agent.send({ text, images }).
 */

import { Agent, CursorAgentError, type Run, type SDKAgent } from '@cursor/sdk';
import { awaitCursorRun, disposeCursorAgent } from '../orchestrator/runMatch.js';
import { resolveLocalAgentStore } from '../orchestrator/resolveLocalAgentStore.js';

export interface ScheduleOcrImage {
  data: string;
  mimeType: string;
}

export interface StartedScheduleOcrRun {
  agent: SDKAgent;
  run: Run;
}

export interface StartScheduleOcrOptions {
  promptText: string;
  image: ScheduleOcrImage;
  cursorApiKey?: string;
  modelId?: string;
  cwd?: string;
}

/**
 * Handshake: create + send с картинкой. До 202 PHP ждёт этот вызов.
 */
export async function startScheduleOcrRun(
  options: StartScheduleOcrOptions,
): Promise<StartedScheduleOcrRun> {
  const apiKey = (options.cursorApiKey ?? process.env.CURSOR_API_KEY ?? '').trim();
  if (!apiKey) {
    throw new Error('CURSOR_API_KEY is required for live schedule OCR (or pass llm_response fixture)');
  }

  const modelId = options.modelId ?? process.env.CURSOR_MODEL ?? 'composer-2.5';
  const cwd = options.cwd ?? process.cwd();
  const localStore = await resolveLocalAgentStore(cwd);

  let agent: SDKAgent | undefined;
  try {
    // Пустой tools — только текст, без shell/read/edit/mcp.
    // settingSources: [] не подхватывает .cursor/mcp.json и правила проекта.
    agent = await Agent.create({
      apiKey,
      model: { id: modelId },
      tools: [],
      local: localStore
        ? { cwd, store: localStore, settingSources: [] }
        : { cwd, settingSources: [] },
    });

    const run = await agent.send({
      text: options.promptText,
      images: [
        {
          data: options.image.data,
          mimeType: options.image.mimeType,
        },
      ],
    });

    return { agent, run };
  } catch (err) {
    if (agent) {
      await disposeCursorAgent(agent);
    }
    throw mapCursorStartupError(err);
  }
}

export { awaitCursorRun, disposeCursorAgent };

function mapCursorStartupError(err: unknown): Error {
  if (err instanceof CursorAgentError) {
    return new Error(
      `Cursor agent startup failed (retryable=${String(err.isRetryable)}): ${err.message}`,
    );
  }
  return err instanceof Error ? err : new Error(String(err));
}
