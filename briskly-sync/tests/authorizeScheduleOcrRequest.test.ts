import { describe, expect, it } from 'vitest';
import { authorizeScheduleOcrRequest } from '../src/http/authorizeScheduleOcrRequest.js';

const env = {
  SCHEDULE_PHOTO_CAPTURE_SECRET: 'photo-secret',
  BRISKLY_SYNC_CAPTURE_SECRET: 'briskly-secret',
};

describe('authorizeScheduleOcrRequest', () => {
  it('верный X-Schedule-Photo-Capture-Secret проходит', () => {
    const result = authorizeScheduleOcrRequest(
      { 'x-schedule-photo-capture-secret': 'photo-secret' },
      env,
    );

    expect(result).toEqual({ ok: true });
  });

  it('нет заголовка — 401', () => {
    const result = authorizeScheduleOcrRequest({}, env);

    expect(result).toEqual({
      ok: false,
      status: 401,
      body: { error: 'unauthorized' },
    });
  });

  it('неверный заголовок — 401', () => {
    const result = authorizeScheduleOcrRequest(
      { 'x-schedule-photo-capture-secret': 'wrong' },
      env,
    );

    expect(result).toEqual({
      ok: false,
      status: 401,
      body: { error: 'unauthorized' },
    });
  });

  it('пустой env — 401', () => {
    const result = authorizeScheduleOcrRequest(
      { 'x-schedule-photo-capture-secret': 'photo-secret' },
      {
        SCHEDULE_PHOTO_CAPTURE_SECRET: '',
        BRISKLY_SYNC_CAPTURE_SECRET: '   ',
      },
    );

    expect(result).toEqual({
      ok: false,
      status: 401,
      body: { error: 'unauthorized' },
    });
  });

  it('секрет другой длины не бросает исключение и отклоняется', () => {
    const result = authorizeScheduleOcrRequest(
      { 'x-schedule-photo-capture-secret': 'x' },
      env,
    );

    expect(result.ok).toBe(false);
  });

  it('без schedule-заголовка принимает X-Briskly-Capture-Secret', () => {
    const result = authorizeScheduleOcrRequest(
      { 'x-briskly-capture-secret': 'photo-secret' },
      env,
    );

    expect(result).toEqual({ ok: true });
  });

  it('пустой SCHEDULE_PHOTO_CAPTURE_SECRET берёт BRISKLY_SYNC_CAPTURE_SECRET', () => {
    const result = authorizeScheduleOcrRequest(
      { 'x-schedule-photo-capture-secret': 'briskly-secret' },
      {
        SCHEDULE_PHOTO_CAPTURE_SECRET: '',
        BRISKLY_SYNC_CAPTURE_SECRET: 'briskly-secret',
      },
    );

    expect(result).toEqual({ ok: true });
  });

  it('неверный schedule-заголовок не подменяется briskly-заголовком', () => {
    const result = authorizeScheduleOcrRequest(
      {
        'x-schedule-photo-capture-secret': 'wrong',
        'x-briskly-capture-secret': 'photo-secret',
      },
      env,
    );

    expect(result.ok).toBe(false);
  });
});
