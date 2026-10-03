#!/usr/bin/env node
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { loadPackageEnv } from '../../cli/loadEnv.js';
import { createFoodSourceServer } from './createFoodSourceServer.js';

loadPackageEnv();

async function main(): Promise<void> {
  const server = createFoodSourceServer();
  const transport = new StdioServerTransport();
  await server.connect(transport);
  console.error('food-source MCP server running on stdio');
}

main().catch((err) => {
  console.error(err instanceof Error ? err.message : String(err));
  process.exit(1);
});
