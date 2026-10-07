import { Agent, CursorAgentError, type Run, type SDKAgent } from '@cursor/sdk';
import { composeAgentPrompt, assertPhpPromptDto } from './buildMatchPrompt.js';
import { classifyMatchResults } from './classifyMatchResults.js';
import { buildMatchMcpServers } from './mcpConfig.js';
import { parseMatchJson } from './parseMatchJson.js';
import { resolveLocalAgentStore } from './resolveLocalAgentStore.js';
import type { MatchRunInput, MatchRunOutput } from './types.js';

export interface StartedCursorRun {
  agent: SDKAgent;
  run: Run;
}

/**
 * Match-run: передаёт PHP ComboCatalogPromptDto в Cursor SDK (или fixture matcher),
 * парсит candidates без price, классифицирует 1D/2B. Apply не вызывает.
 * CLI / offline: синхронный полный цикл. Sidecar HTTP — через startCursorRun + awaitCursorRun.
 */
export async function runMatch(input: MatchRunInput): Promise<MatchRunOutput> {
  assertPhpPromptDto(input.prompt);
  const agentPrompt = composeAgentPrompt(input.prompt);

  const rawText = input.matcher
    ? await input.matcher(input.prompt)
    : await runCursorMatch(agentPrompt, input);

  const matchLines = parseMatchJson(rawText);
  const syncResults = classifyMatchResults({
    sourceLines: input.sourceLines,
    brisklySnapshot: input.brisklySnapshot,
    matchLines,
    cap: input.sectionCap,
  });

  return {
    matchLines,
    syncResults,
    rawText,
    systemUsed: input.prompt.system,
  };
}

/**
 * Handshake: Agent.create + agent.send. До завершения PHP не получает 202.
 */
export async function startCursorRun(
  agentPrompt: string,
  input: MatchRunInput,
): Promise<StartedCursorRun> {
  const apiKey = (input.cursorApiKey ?? process.env.CURSOR_API_KEY ?? '').trim();
  if (!apiKey) {
    throw new Error('CURSOR_API_KEY is required for live match (or pass matcher for fixture mode)');
  }

  const modelId = input.modelId ?? process.env.CURSOR_MODEL ?? 'composer-2.5';
  const cwd = input.cwd ?? process.cwd();
  const mcpServers = input.enableMcp ? buildMatchMcpServers(input.mcp) : undefined;

  const localStore = await resolveLocalAgentStore(cwd);

  let agent: SDKAgent | undefined;
  try {
    agent = await Agent.create({
      apiKey,
      model: { id: modelId },
      local: localStore ? { cwd, store: localStore } : { cwd },
      ...(mcpServers ? { mcpServers } : {}),
    });

    const run = await agent.send(agentPrompt);
    return { agent, run };
  } catch (err) {
    if (agent) {
      await disposeCursorAgent(agent);
    }
    throw mapCursorStartupError(err);
  }
}

/**
 * Фон после handshake: stream + wait → текст ассистента.
 */
export async function awaitCursorRun(run: Run): Promise<string> {
  let streamed = '';
  if (typeof run.stream === 'function') {
    for await (const event of run.stream()) {
      if (event.type === 'assistant' && event.message?.content) {
        streamed += flattenContent(event.message.content);
      }
    }
  }

  const result = await run.wait();
  if (result.status === 'error') {
    throw new Error(`Cursor match run failed: ${result.id}`);
  }

  const fromResult =
    typeof result.result === 'string'
      ? result.result
      : flattenContent(
          (result as { result?: { content?: Array<{ type: string; text?: string }> } }).result
            ?.content,
        );

  const text = (streamed || fromResult).trim();
  if (!text) {
    throw new Error('Cursor match returned empty assistant text');
  }
  return text;
}

export async function disposeCursorAgent(agent: SDKAgent): Promise<void> {
  try {
    await agent[Symbol.asyncDispose]();
  } catch {
    try {
      agent.close();
    } catch {
      // best-effort
    }
  }
}

async function runCursorMatch(agentPrompt: string, input: MatchRunInput): Promise<string> {
  const started = await startCursorRun(agentPrompt, input);
  try {
    return await awaitCursorRun(started.run);
  } finally {
    await disposeCursorAgent(started.agent);
  }
}

function mapCursorStartupError(err: unknown): Error {
  if (err instanceof CursorAgentError) {
    return new Error(
      `Cursor agent startup failed (retryable=${String(err.isRetryable)}): ${err.message}`,
    );
  }
  return err instanceof Error ? err : new Error(String(err));
}

function flattenContent(
  content: Array<{ type: string; text?: string }> | string | undefined,
): string {
  if (typeof content === 'string') {
    return content;
  }
  if (!Array.isArray(content)) {
    return '';
  }
  return content
    .filter((block) => block.type === 'text' && typeof block.text === 'string')
    .map((block) => block.text ?? '')
    .join('');
}
