/**
 * Одноразовый live ping для автотеста (subprocess, чтобы не ронять vitest worker).
 * stdout: строка PONG при успехе; иначе exit 1.
 */
import { loadPackageEnv } from '../src/cli/loadEnv.js';
import { LLM_PING_EXPECTED, pingLlm } from '../src/orchestrator/pingLlm.js';

loadPackageEnv();

pingLlm()
  .then((reply) => {
    if (reply !== LLM_PING_EXPECTED) {
      console.error(`unexpected reply: ${reply}`);
      process.exit(1);
    }
    console.log(reply);
  })
  .catch((err: unknown) => {
    console.error(err instanceof Error ? err.message : String(err));
    process.exit(1);
  });
