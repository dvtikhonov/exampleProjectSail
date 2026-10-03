import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import type { MatchRunInput } from './types.js';

const here = dirname(fileURLToPath(import.meta.url));
const packageRoot = join(here, '..', '..');

/**
 * Inline MCP config для Cursor Agent.create / send.
 * food-source + briskly-target; секреты только в env процесса MCP.
 */
export function buildMatchMcpServers(options: MatchRunInput['mcp'] = {}): Record<
  string,
  {
    type: 'stdio';
    command: string;
    args: string[];
    env?: Record<string, string>;
    cwd?: string;
  }
> {
  const env = { ...process.env, ...(options.env ?? {}) } as Record<string, string>;
  // Не протаскиваем CURSOR_API_KEY в MCP child без нужды.
  delete env.CURSOR_API_KEY;

  const foodCommand = options.foodSourceCommand ?? process.execPath;
  const foodArgs =
    options.foodSourceArgs ??
    [join(packageRoot, 'dist', 'mcp', 'foodSource', 'runFoodSourceMcp.js')];

  const brisklyCommand = options.brisklyTargetCommand ?? process.execPath;
  const brisklyArgs =
    options.brisklyTargetArgs ??
    [join(packageRoot, 'dist', 'mcp', 'brisklyTarget', 'runBrisklyTargetMcp.js')];

  return {
    'food-source': {
      type: 'stdio',
      command: foodCommand,
      args: foodArgs,
      env,
      cwd: packageRoot,
    },
    'briskly-target': {
      type: 'stdio',
      command: brisklyCommand,
      args: brisklyArgs,
      env,
      cwd: packageRoot,
    },
  };
}
