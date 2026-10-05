import { existsSync, readFileSync, writeFileSync } from 'node:fs';

/**
 * Обновляет или добавляет BRISKLY_TOKEN в .env. Остальные ключи не трогает.
 */
export function writeBrisklyTokenToEnv(envPath: string, token: string): void {
  const newLine = `BRISKLY_TOKEN=${formatEnvValue(token)}`;

  if (!existsSync(envPath)) {
    writeFileSync(envPath, `${newLine}\n`, 'utf8');
    return;
  }

  const text = readFileSync(envPath, 'utf8');
  const endsWithNewline = text.endsWith('\n');
  const lines = text.split(/\r?\n/);
  // split оставляет хвостовую пустую строку, если файл оканчивался на \n
  if (endsWithNewline && lines.length > 0 && lines[lines.length - 1] === '') {
    lines.pop();
  }

  let replaced = false;
  const next = lines.map((rawLine) => {
    const trimmed = rawLine.trim();
    if (!trimmed || trimmed.startsWith('#')) {
      return rawLine;
    }
    const eq = trimmed.indexOf('=');
    if (eq <= 0) {
      return rawLine;
    }
    const key = trimmed.slice(0, eq).trim();
    if (key !== 'BRISKLY_TOKEN') {
      return rawLine;
    }
    replaced = true;
    return newLine;
  });

  if (!replaced) {
    next.push(newLine);
  }

  writeFileSync(envPath, `${next.join('\n')}\n`, 'utf8');
}

function formatEnvValue(value: string): string {
  if (/[\s#"']/.test(value) || value === '') {
    return `"${value.replace(/\\/g, '\\\\').replace(/"/g, '\\"')}"`;
  }
  return value;
}
