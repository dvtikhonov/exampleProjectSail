import { mkdir, mkdtemp, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { afterEach, describe, expect, it } from 'vitest';
import { readAliasesMarkdown } from '../src/scheduleOcr/readAliasesMarkdown.js';

describe('readAliasesMarkdown', () => {
  const dirs: string[] = [];

  afterEach(async () => {
    await Promise.all(dirs.splice(0).map((d) => rm(d, { recursive: true, force: true })));
  });

  it('читает explicit path первым', async () => {
    const dir = await mkdtemp(join(tmpdir(), 'aliases-'));
    dirs.push(dir);
    const explicit = join(dir, 'explicit.md');
    await writeFile(explicit, '| OCR | Канон |\n| a | A |\n', 'utf8');

    const md = await readAliasesMarkdown({
      explicitPath: explicit,
      packageRoot: join(dir, 'briskly-sync'),
    });
    expect(md).toContain('| a | A |');
  });

  it('предпочитает .cursor/commands перед service-c', async () => {
    const root = await mkdtemp(join(tmpdir(), 'aliases-root-'));
    dirs.push(root);
    const packageRoot = join(root, 'briskly-sync');
    const commandsDir = join(root, '.cursor', 'commands');
    const serviceC = join(root, 'service-c');
    await mkdir(commandsDir, { recursive: true });
    await mkdir(serviceC, { recursive: true });
    await mkdir(packageRoot, { recursive: true });
    await writeFile(
      join(commandsDir, 'phototext-dish-aliases.md'),
      '| OCR | Канон |\n| from-commands | CommandsCanon |\n',
      'utf8',
    );
    await writeFile(
      join(serviceC, 'phototext-dish-aliases.md'),
      '| OCR | Канон |\n| from-service-c | ServiceCanon |\n',
      'utf8',
    );

    const md = await readAliasesMarkdown({ packageRoot });
    expect(md).toContain('CommandsCanon');
    expect(md).not.toContain('ServiceCanon');
  });

  it('fallback на service-c если commands отсутствует', async () => {
    const root = await mkdtemp(join(tmpdir(), 'aliases-fb-'));
    dirs.push(root);
    const packageRoot = join(root, 'briskly-sync');
    const serviceC = join(root, 'service-c');
    await mkdir(serviceC, { recursive: true });
    await mkdir(packageRoot, { recursive: true });
    await writeFile(
      join(serviceC, 'phototext-dish-aliases.md'),
      '| OCR | Канон |\n| пюре | Картофельное пюре |\n',
      'utf8',
    );

    const md = await readAliasesMarkdown({ packageRoot });
    expect(md).toContain('Картофельное пюре');
  });

  it('пустая строка если файлов нет', async () => {
    const root = await mkdtemp(join(tmpdir(), 'aliases-miss-'));
    dirs.push(root);
    const md = await readAliasesMarkdown({ packageRoot: join(root, 'briskly-sync') });
    expect(md).toBe('');
  });
});
