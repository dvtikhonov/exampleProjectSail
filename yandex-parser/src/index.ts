import express, { type NextFunction, type Request, type Response } from 'express';
import { config } from './config.js';
import { closeBrowser } from './browser.js';
import { resolveOrganization } from './resolve/resolveOrganization.js';
import { syncReviews } from './sync/syncReviews.js';
import type {
  ApiErrorBody,
  ResolveCollectResponseBody,
  ResolveRequestBody,
  SyncReviewsRequestBody,
  SyncReviewsResponseBody,
} from './types.js';
import { isYandexMapsUrl } from './utils/yandexUrl.js';

const app = express();
app.use(express.json({ limit: '1mb' }));

function sendError(res: Response, status: number, error: string, message: string): void {
  const body: ApiErrorBody = { error, message };
  res.status(status).json(body);
}

app.get('/health', (_req, res) => {
  res.json({ status: 'ok' });
});

app.post('/resolve', async (req: Request, res: Response, next: NextFunction) => {
  try {
    const body = req.body as ResolveRequestBody;

    if (!body?.url || typeof body.url !== 'string') {
      sendError(res, 400, 'validation_error', 'Field "url" is required.');
      return;
    }

    if (!isYandexMapsUrl(body.url)) {
      sendError(res, 422, 'invalid_url', 'URL must point to yandex.ru/com/kz/com.tr maps.');
      return;
    }

    const response: ResolveCollectResponseBody = await resolveOrganization(body.url);

    res.json(response);
  } catch (error) {
    next(error);
  }
});

app.post('/sync-reviews', async (req: Request, res: Response, next: NextFunction) => {
  try {
    const body = req.body as SyncReviewsRequestBody;

    if (!body?.org_id || typeof body.org_id !== 'string') {
      sendError(res, 400, 'validation_error', 'Field "org_id" is required.');
      return;
    }

    if (!body?.canonical_url || typeof body.canonical_url !== 'string') {
      sendError(res, 400, 'validation_error', 'Field "canonical_url" is required.');
      return;
    }

    if (!/^\d+$/.test(body.org_id)) {
      sendError(res, 422, 'invalid_org_id', 'Field "org_id" must be numeric.');
      return;
    }

    if (!isYandexMapsUrl(body.canonical_url)) {
      sendError(res, 422, 'invalid_url', 'Field "canonical_url" must be a Yandex Maps URL.');
      return;
    }

    const stopAnchors = Array.isArray(body.stop_anchors)
      ? body.stop_anchors.filter((anchor): anchor is string => typeof anchor === 'string' && anchor.trim() !== '')
      : [];

    const result = await syncReviews({
      org_id: body.org_id,
      canonical_url: body.canonical_url,
      stop_anchors: stopAnchors,
    });

    const response: SyncReviewsResponseBody = {
      org: result.org,
      reviews: result.reviews,
    };

    res.json(response);
  } catch (error) {
    next(error);
  }
});

app.use((error: unknown, _req: Request, res: Response, _next: NextFunction) => {
  const message = error instanceof Error ? error.message : 'Unknown parser error';
  console.error('[yandex-parser]', message);
  sendError(res, 500, 'parser_error', message);
});

const server = app.listen(config.port, () => {
  console.log(`[yandex-parser] listening on port ${config.port}`);
});

async function shutdown(signal: string): Promise<void> {
  console.log(`[yandex-parser] received ${signal}, shutting down...`);
  server.close();
  await closeBrowser();
  process.exit(0);
}

process.on('SIGINT', () => {
  void shutdown('SIGINT');
});

process.on('SIGTERM', () => {
  void shutdown('SIGTERM');
});

export { app };
