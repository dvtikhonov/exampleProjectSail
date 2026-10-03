import { existsSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * Подхватывает KEY=VALUE из .env рядом с пакетом (не перезаписывает уже заданные env).
 * Без зависимости dotenv; токен в лог не печатает.
 */
export function loadPackageEnv(envFileName = '.env'): string | null {
  const here = dirname(fileURLToPath(import.meta.url));
  // src/cli → ../../.env ; dist/cli → ../../.env
  const packageRoot = join(here, '..', '..');
  const envPath = join(packageRoot, envFileName);

  if (!existsSync(envPath)) {
    return null;
  }

  const text = readFileSync(envPath, 'utf8');
  for (const rawLine of text.split(/\r?\n/)) {
    const line = rawLine.trim();
    if (!line || line.startsWith('#')) {
      continue;
    }
    const eq = line.indexOf('=');
    if (eq <= 0) {
      continue;
    }
    const key = line.slice(0, eq).trim();
    let value = line.slice(eq + 1).trim();
    if (
      (value.startsWith('"') && value.endsWith('"')) ||
      (value.startsWith("'") && value.endsWith("'"))
    ) {
      value = value.slice(1, -1);
    }
    if (process.env[key] === undefined) {
      process.env[key] = value;
    }
  }

  return envPath;
}
