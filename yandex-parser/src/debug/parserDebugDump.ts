/**

 * Отладочный дамп для выбранных org_id: NDJSON в файл и опционально ingest по HTTP.

 */

import { appendFileSync, mkdirSync } from 'node:fs';

import { join } from 'node:path';



/** Идентификатор отладочной сессии в логах. */

const SESSION_ID = '029acb';

/** Локальный endpoint для стриминга NDJSON (игнорируется при ошибке сети). */

const INGEST_URL = 'http://127.0.0.1:7733/ingest/c27e23f0-3066-42e9-a51b-eec8b6075cfb';



/** org_id, для которых включён дамп (DEBUG_ORG_IDS, через запятую). */

const debugOrgIds = new Set(

  (process.env.DEBUG_ORG_IDS ?? '115272305870')

    .split(',')

    .map((value) => value.trim())

    .filter((value) => value !== ''),

);



const dumpDir = process.env.PARSER_DEBUG_DUMP_DIR ?? '/app/debug-dumps';

const workspaceLogPath = process.env.DEBUG_LOG_PATH ?? `/workspace/debug-${SESSION_ID}.log`;



/** Включён ли отладочный дамп для данного org_id. */

export function isParserDebugOrg(orgId: string): boolean {

  return debugOrgIds.has(orgId);

}



interface DebugLogPayload {

  hypothesisId: string;

  location: string;

  message: string;

  data: unknown;

  runId?: string;

}



/** Одна NDJSON-строка в лог и ingest (только для DEBUG_ORG_IDS). */

export function parserDebugLog(orgId: string, payload: DebugLogPayload): void {

  if (!isParserDebugOrg(orgId)) {

    return;

  }



  const line = JSON.stringify({

    sessionId: SESSION_ID,

    timestamp: Date.now(),

    ...payload,

  });



  // #region agent log

  void fetch(INGEST_URL, {

    method: 'POST',

    headers: {

      'Content-Type': 'application/json',

      'X-Debug-Session-Id': SESSION_ID,

    },

    body: line,

  }).catch(() => {});



  for (const filePath of [join(dumpDir, `debug-${SESSION_ID}.log`), workspaceLogPath]) {

    try {

      mkdirSync(filePath.includes('/') ? filePath.slice(0, filePath.lastIndexOf('/')) : dumpDir, {

        recursive: true,

      });

      appendFileSync(filePath, `${line}\n`);

    } catch {

      // Ignore write errors (e.g. path not mounted in container).

    }

  }

  // #endregion

}



/** Файл org-{id}-{timestamp}.ndjson с секциями дампа парсера. */

export function writeParserDataDump(orgId: string, sections: Record<string, unknown>[]): void {

  if (!isParserDebugOrg(orgId)) {

    return;

  }



  const timestamp = new Date().toISOString().replace(/[:.]/g, '-');

  const dumpPath = join(dumpDir, `org-${orgId}-${timestamp}.ndjson`);



  try {

    mkdirSync(dumpDir, { recursive: true });



    for (const section of sections) {

      appendFileSync(dumpPath, `${JSON.stringify(section)}\n`);

    }



    parserDebugLog(orgId, {

      hypothesisId: 'DUMP',

      location: 'parserDebugDump.ts:writeParserDataDump',

      message: 'Parser data dump written',

      data: { dumpPath, sections: sections.length },

    });

  } catch (error) {

    parserDebugLog(orgId, {

      hypothesisId: 'DUMP',

      location: 'parserDebugDump.ts:writeParserDataDump',

      message: 'Failed to write parser data dump',

      data: { error: error instanceof Error ? error.message : String(error) },

    });

  }

}

