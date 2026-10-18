import { mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { createMock, sendMock } = vi.hoisted(() => ({
  createMock: vi.fn(),
  sendMock: vi.fn(),
}));

vi.mock('@cursor/sdk', () => ({
  Agent: {
    create: createMock,
  },
  CursorAgentError: class CursorAgentError extends Error {
    isRetryable = false;
  },
  JsonlLocalAgentStore: class JsonlLocalAgentStore {
    constructor(readonly rootDir: string) {}
  },
}));

import { startScheduleOcrRun } from '../src/scheduleOcr/runScheduleOcr.js';

describe('startScheduleOcrRun', () => {
  beforeEach(() => {
    sendMock.mockReset();
    sendMock.mockResolvedValue({ id: 'run-1' });
    createMock.mockReset();
    createMock.mockResolvedValue({
      send: sendMock,
      close: () => undefined,
      [Symbol.asyncDispose]: async () => undefined,
    });
  });

  it('передаёт Agent.create tools: [] и settingSources: []', async () => {
    const cwd = await mkdtemp(path.join(tmpdir(), 'schedule-ocr-agent-'));
    try {
      await startScheduleOcrRun({
        promptText: 'ocr',
        image: { data: 'aGVsbG8=', mimeType: 'image/jpeg' },
        cursorApiKey: 'test-key',
        modelId: 'composer-2.5',
        cwd,
      });
    } finally {
      await rm(cwd, { recursive: true, force: true });
    }

    expect(createMock).toHaveBeenCalledTimes(1);
    expect(createMock).toHaveBeenCalledWith(
      expect.objectContaining({
        apiKey: 'test-key',
        model: { id: 'composer-2.5' },
        tools: [],
        local: expect.objectContaining({
          cwd,
          settingSources: [],
        }),
      }),
    );
    expect(sendMock).toHaveBeenCalledWith({
      text: 'ocr',
      images: [{ data: 'aGVsbG8=', mimeType: 'image/jpeg' }],
    });
  });

  it('по умолчанию передаёт model default (Auto)', async () => {
    const cwd = await mkdtemp(path.join(tmpdir(), 'schedule-ocr-auto-'));
    const prevModel = process.env.CURSOR_MODEL;
    const prevOpt = process.env.CURSOR_OPTIMIZE_FOR;
    delete process.env.CURSOR_MODEL;
    delete process.env.CURSOR_OPTIMIZE_FOR;
    try {
      await startScheduleOcrRun({
        promptText: 'ocr',
        image: { data: 'aGVsbG8=', mimeType: 'image/jpeg' },
        cursorApiKey: 'test-key',
        cwd,
      });
    } finally {
      await rm(cwd, { recursive: true, force: true });
      if (prevModel === undefined) {
        delete process.env.CURSOR_MODEL;
      } else {
        process.env.CURSOR_MODEL = prevModel;
      }
      if (prevOpt === undefined) {
        delete process.env.CURSOR_OPTIMIZE_FOR;
      } else {
        process.env.CURSOR_OPTIMIZE_FOR = prevOpt;
      }
    }

    expect(createMock).toHaveBeenCalledWith(
      expect.objectContaining({
        model: { id: 'default' },
      }),
    );
  });
});

