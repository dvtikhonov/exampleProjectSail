import { Agent, CursorAgentError } from '@cursor/sdk';
import { composeAgentPrompt, assertPhpPromptDto } from './buildMatchPrompt.js';
import { classifyMatchResults } from './classifyMatchResults.js';
import { buildMatchMcpServers } from './mcpConfig.js';
import { parseMatchJson } from './parseMatchJson.js';
import type { MatchRunInput, MatchRunOutput } from './types.js';

/**
 * Match-run: передаёт PHP ComboCatalogPromptDto в Cursor SDK (или fixture matcher),
 * парсит candidates без price, классифицирует 1D/2B. Apply не вызывает.
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

async function runCursorMatch(agentPrompt: string, input: MatchRunInput): Promise<string> {
  const apiKey = (input.cursorApiKey ?? process.env.CURSOR_API_KEY ?? '').trim();
  if (!apiKey) {
    throw new Error('CURSOR_API_KEY is required for live match (or pass matcher for fixture mode)');
  }

  const modelId = input.modelId ?? process.env.CURSOR_MODEL ?? 'composer-2.5';
  const cwd = input.cwd ?? process.cwd();
  const mcpServers = input.enableMcp ? buildMatchMcpServers(input.mcp) : undefined;

  try {
    await using agent = await Agent.create({
      apiKey,
      model: { id: modelId },
      local: { cwd },
      ...(mcpServers ? { mcpServers } : {}),
    });

    const run = await agent.send(agentPrompt);

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
  } catch (err) {
    if (err instanceof CursorAgentError) {
      throw new Error(
        `Cursor agent startup failed (retryable=${String(err.isRetryable)}): ${err.message}`,
      );
    }
    throw err;
  }
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
