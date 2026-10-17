/**
 * Словарь OCR→канон из phototext-dish-aliases.md (без CRUD/БД).
 * Порядок: SCHEDULE_PHOTO_ALIASES_PATH → .cursor/commands → service-c (fallback).
 */

import { readFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

export interface ReadAliasesMarkdownOptions {
  /** Явный путь (env SCHEDULE_PHOTO_ALIASES_PATH). */
  explicitPath?: string;
  /** Корень пакета briskly-sync (для относительных fallback). */
  packageRoot?: string;
  readFile?: (path: string, encoding: 'utf8') => Promise<string>;
}

/**
 * Читает markdown-словарь сокращений: первый существующий непустой файл.
 */
export async function readAliasesMarkdown(
  options: ReadAliasesMarkdownOptions = {},
): Promise<string> {
  const packageRoot =
    options.packageRoot
    ?? join(dirname(fileURLToPath(import.meta.url)), '../..');
  const read = options.readFile ?? ((path, encoding) => readFile(path, encoding));

  const candidates = [
    (options.explicitPath ?? '').trim(),
    join(packageRoot, '../.cursor/commands/phototext-dish-aliases.md'),
    join(packageRoot, '../service-c/phototext-dish-aliases.md'),
  ].filter((p) => p !== '');

  for (const path of candidates) {
    try {
      const text = await read(path, 'utf8');
      if (text.trim() !== '') {
        return text;
      }
    } catch {
      // try next
    }
  }

  return '';
}
