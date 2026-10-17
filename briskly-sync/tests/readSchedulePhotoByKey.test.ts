import { mkdtemp, mkdir, rm, symlink, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { describe, expect, it } from 'vitest';
import { readSchedulePhotoByKey } from '../src/scheduleOcr/readSchedulePhotoByKey.js';

const SECRET = 'UNIQUE_OUTSIDE_BYTES_9f3a2c';

async function withDirs(
  run: (dirs: { root: string; outside: string }) => Promise<void>,
): Promise<void> {
  const parent = await mkdtemp(path.join(tmpdir(), 'schedule-photo-'));
  const root = path.join(parent, 'root');
  const outside = path.join(parent, 'outside');
  await mkdir(root);
  await mkdir(outside);
  try {
    await run({ root, outside });
  } finally {
    await rm(parent, { recursive: true, force: true });
  }
}

describe('readSchedulePhotoByKey', () => {
  it('читает ключ внутри временного корня', async () => {
    await withDirs(async ({ root }) => {
      const key = 'nested/menu.jpg';
      const bytes = Buffer.from('inside-jpeg');
      await mkdir(path.join(root, 'nested'));
      await writeFile(path.join(root, key), bytes);

      const result = await readSchedulePhotoByKey({ photoRoot: root, photoKey: key });

      expect(result).toEqual({ ok: true, bytes, mimeType: 'image/jpeg' });
    });
  });

  it('отклоняет абсолютный путь и не отдаёт содержимое файла', async () => {
    await withDirs(async ({ root, outside }) => {
      const file = path.join(outside, 'secret.jpg');
      await writeFile(file, Buffer.from(SECRET));

      const result = await readSchedulePhotoByKey({ photoRoot: root, photoKey: file });

      expect(result.ok).toBe(false);
      if (!result.ok) {
        expect(result.status).toBe(400);
      }
      expect(JSON.stringify(result)).not.toContain(SECRET);
    });
  });

  it('отклоняет .. за пределы корня', async () => {
    await withDirs(async ({ root, outside }) => {
      await writeFile(path.join(outside, 'secret.jpg'), Buffer.from(SECRET));
      const key = path.relative(root, path.join(outside, 'secret.jpg'));
      expect(key.startsWith('..')).toBe(true);

      const result = await readSchedulePhotoByKey({ photoRoot: root, photoKey: key });

      expect(result.ok).toBe(false);
      if (!result.ok) {
        expect(result.status).toBe(400);
      }
      expect(JSON.stringify(result)).not.toContain(SECRET);
    });
  });

  it('отклоняет симлинк наружу', async () => {
    await withDirs(async ({ root, outside }) => {
      const target = path.join(outside, 'secret.jpg');
      await writeFile(target, Buffer.from(SECRET));
      await symlink(target, path.join(root, 'link.jpg'));

      const result = await readSchedulePhotoByKey({ photoRoot: root, photoKey: 'link.jpg' });

      expect(result.ok).toBe(false);
      if (!result.ok) {
        expect(result.status).toBe(400);
      }
      expect(JSON.stringify(result)).not.toContain(SECRET);
    });
  });

  it('пустой корень — 503, файл не читается', async () => {
    await withDirs(async ({ outside }) => {
      const file = path.join(outside, 'secret.jpg');
      await writeFile(file, Buffer.from(SECRET));

      const result = await readSchedulePhotoByKey({ photoRoot: '  ', photoKey: file });

      expect(result).toEqual({
        ok: false,
        status: 503,
        error: 'photo_root_unconfigured',
        message: 'SCHEDULE_PHOTO_PHOTO_ROOT is required',
      });
      expect(JSON.stringify(result)).not.toContain(SECRET);
    });
  });

  it('отклоняет NUL в ключе', async () => {
    await withDirs(async ({ root }) => {
      await writeFile(path.join(root, 'menu.jpg'), Buffer.from(SECRET));

      const result = await readSchedulePhotoByKey({
        photoRoot: root,
        photoKey: 'menu.jpg\0.png',
      });

      expect(result.ok).toBe(false);
      if (!result.ok) {
        expect(result.status).toBe(400);
      }
      expect(JSON.stringify(result)).not.toContain(SECRET);
    });
  });
});
