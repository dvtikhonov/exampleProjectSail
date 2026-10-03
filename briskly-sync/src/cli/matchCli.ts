#!/usr/bin/env node
/**
 * CLI match: fixture (offline) или live Cursor SDK.
 *
 * Fixture:
 *   npm run match -- --fixture fixtures/match-run.json
 *
 * Live (нужен CURSOR_API_KEY + prompt/source/snapshot JSON из PHP):
 *   npm run match -- --prompt prompt.json --source source.json --snapshot briskly.json [--mcp]
 */

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { loadPackageEnv } from './loadEnv.js';
import { looksLikeEnumMatchNamesSystem } from '../orchestrator/buildMatchPrompt.js';
import { runMatch } from '../orchestrator/runMatch.js';
import type {
  BrisklySnapshotItem,
  ComboCatalogPromptDto,
  SourceMenuLine,
} from '../orchestrator/types.js';

loadPackageEnv();

interface MatchFixtureFile {
  prompt: ComboCatalogPromptDto;
  source_lines: SourceMenuLine[];
  briskly_snapshot: BrisklySnapshotItem[];
  /** Offline LLM response (без Cursor). */
  llm_response?: string;
}

function printHelp(): void {
  console.log(`Usage:
  npm run match -- --fixture fixtures/match-run.json
  npm run match -- --prompt prompt.json --source source.json --snapshot briskly.json [--mcp]

Fixture mode returns candidates + sync-results (no apply).
Prompt system/user must come from PHP ComboCatalogPromptDto (ENUM + PromptBuilder).
`);
}

function readJson<T>(path: string): T {
  return JSON.parse(readFileSync(resolve(path), 'utf8')) as T;
}

function getFlag(args: string[], name: string): string | undefined {
  const idx = args.indexOf(name);
  if (idx === -1) {
    return undefined;
  }
  return args[idx + 1];
}

async function main(): Promise<void> {
  const args = process.argv.slice(2);
  if (args.includes('--help') || args.length === 0) {
    printHelp();
    process.exit(args.length === 0 ? 1 : 0);
  }

  const fixturePath = getFlag(args, '--fixture');
  if (fixturePath) {
    const fixture = readJson<MatchFixtureFile>(fixturePath);
    if (!looksLikeEnumMatchNamesSystem(fixture.prompt.system)) {
      console.error('Fixture prompt.system does not look like PHP ENUM MatchNames system');
      process.exit(1);
    }

    const llmResponse = fixture.llm_response;
    if (!llmResponse) {
      console.error('Fixture requires llm_response for offline match');
      process.exit(1);
    }

    const output = await runMatch({
      prompt: fixture.prompt,
      sourceLines: fixture.source_lines,
      brisklySnapshot: fixture.briskly_snapshot,
      matcher: async () => llmResponse,
    });

    console.log(
      JSON.stringify(
        {
          system_from_php_enum: looksLikeEnumMatchNamesSystem(output.systemUsed),
          match_lines: output.matchLines,
          sync_results: output.syncResults,
        },
        null,
        2,
      ),
    );
    return;
  }

  const promptPath = getFlag(args, '--prompt');
  const sourcePath = getFlag(args, '--source');
  const snapshotPath = getFlag(args, '--snapshot');
  if (!promptPath || !sourcePath || !snapshotPath) {
    printHelp();
    process.exit(1);
  }

  const prompt = readJson<ComboCatalogPromptDto>(promptPath);
  const sourceLines = readJson<SourceMenuLine[] | { source_lines: SourceMenuLine[] }>(sourcePath);
  const snapshot = readJson<BrisklySnapshotItem[] | { items: BrisklySnapshotItem[] }>(snapshotPath);

  const output = await runMatch({
    prompt,
    sourceLines: Array.isArray(sourceLines) ? sourceLines : sourceLines.source_lines,
    brisklySnapshot: Array.isArray(snapshot) ? snapshot : snapshot.items,
    enableMcp: args.includes('--mcp'),
  });

  console.log(
    JSON.stringify(
      {
        match_lines: output.matchLines,
        sync_results: output.syncResults,
      },
      null,
      2,
    ),
  );
}

main().catch((err) => {
  console.error(err instanceof Error ? err.message : String(err));
  process.exit(1);
});
