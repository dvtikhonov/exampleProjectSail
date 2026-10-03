#!/usr/bin/env node
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { loadPackageEnv } from '../../cli/loadEnv.js';
import { createBrisklyTargetServer } from './createBrisklyTargetServer.js';

loadPackageEnv();

async function main(): Promise<void> {
  const server = createBrisklyTargetServer();
  const transport = new StdioServerTransport();
  await server.connect(transport);
  console.error('briskly-target MCP server running on stdio');
}

main().catch((err) => {
  console.error(err instanceof Error ? err.message : String(err));
  process.exit(1);
});
