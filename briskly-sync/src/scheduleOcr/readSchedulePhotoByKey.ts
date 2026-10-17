/**
 * Чтение фото расписания по относительному photo_key.
 * Корень только SCHEDULE_PHOTO_PHOTO_ROOT; выход за каталог не отдаёт байты файла.
 */

import { readFile, realpath } from 'node:fs/promises';
import path from 'node:path';

const MIME_BY_EXT = {
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.png': 'image/png',
  '.gif': 'image/gif',
  '.webp': 'image/webp',
} as const;

export type SchedulePhotoMimeType = (typeof MIME_BY_EXT)[keyof typeof MIME_BY_EXT];

export interface ReadSchedulePhotoByKeyOptions {
  photoRoot: string;
  photoKey: string;
}

export type ReadSchedulePhotoByKeyResult =
  | { ok: true; bytes: Buffer; mimeType: SchedulePhotoMimeType }
  | {
      ok: false;
      status: 400 | 503;
      error: 'invalid_photo_key' | 'photo_root_unconfigured';
      message: string;
    };

/**
 * Пустой корень — 503 и диск не трогаем.
 * Иначе path.resolve(root, key), realpath, и файл обязан лежать внутри realpath(root).
 */
export async function readSchedulePhotoByKey(
  options: ReadSchedulePhotoByKeyOptions,
): Promise<ReadSchedulePhotoByKeyResult> {
  const photoRoot = options.photoRoot.trim();
  if (photoRoot === '') {
    return {
      ok: false,
      status: 503,
      error: 'photo_root_unconfigured',
      message: 'SCHEDULE_PHOTO_PHOTO_ROOT is required',
    };
  }

  if (options.photoKey.includes('\0')) {
    return relativeKeyRejected();
  }

  const photoKey = options.photoKey.trim();
  if (photoKey === '' || path.posix.isAbsolute(photoKey) || path.win32.isAbsolute(photoKey)) {
    return relativeKeyRejected();
  }

  const mimeType = mimeFromKey(photoKey);
  if (mimeType === undefined) {
    return {
      ok: false,
      status: 400,
      error: 'invalid_photo_key',
      message: 'photo_key extension must be jpg, jpeg, png, gif, or webp',
    };
  }

  let rootReal: string;
  try {
    rootReal = await realpath(photoRoot);
  } catch {
    return {
      ok: false,
      status: 503,
      error: 'photo_root_unconfigured',
      message: 'SCHEDULE_PHOTO_PHOTO_ROOT is unavailable',
    };
  }

  const resolved = path.resolve(photoRoot, photoKey);
  let fileReal: string;
  try {
    fileReal = await realpath(resolved);
  } catch {
    return unreadableKey();
  }

  const rootPrefix = rootReal.endsWith(path.sep) ? rootReal : `${rootReal}${path.sep}`;
  if (!fileReal.startsWith(rootPrefix)) {
    return unreadableKey();
  }

  try {
    const bytes = await readFile(fileReal);
    return { ok: true, bytes, mimeType };
  } catch {
    return unreadableKey();
  }
}

function relativeKeyRejected(): ReadSchedulePhotoByKeyResult {
  return {
    ok: false,
    status: 400,
    error: 'invalid_photo_key',
    message: 'photo_key must be a relative path',
  };
}

function mimeFromKey(photoKey: string): SchedulePhotoMimeType | undefined {
  const ext = path.posix.extname(photoKey).toLowerCase();
  return MIME_BY_EXT[ext as keyof typeof MIME_BY_EXT];
}

function unreadableKey(): ReadSchedulePhotoByKeyResult {
  return {
    ok: false,
    status: 400,
    error: 'invalid_photo_key',
    message: 'photo_key must stay inside SCHEDULE_PHOTO_PHOTO_ROOT',
  };
}
