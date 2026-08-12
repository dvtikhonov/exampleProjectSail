import http from 'k6/http';
import { check, sleep } from 'k6';
import { SharedArray } from 'k6/data';
import { Trend } from 'k6/metrics';
import { vu } from 'k6/execution';

/**
 * Полный клиентский поток service-c: рестораны → меню → корзина → адрес → submit.
 *
 * Профиль по умолчанию (PROFILE=deep) — углублённая проверка:
 *   - ramp 15 → 50 → 100 VU (поиск точки деградации);
 *   - soak ~15 мин на пике (утечки / деградация во времени);
 *   - пороги p(95)/p(99) submit только для scenario=stress_soak;
 *     Trend food_submit_* по хвосту latency;
 *   - параллельно competitive (10 VU, хвост пула из 5 токенов, та же привязка
 *     ресторана) и negative (5 VU, невалидные запросы);
 *   - пик одновременных VU ≈115 (100 stress + 10 competitive + 5 negative).
 *   Модель: один user → один restaurant → одна draft-корзина.
 *
 * PROFILE=smoke — короткий прогон 5→15 VU (прежний smoke).
 * PROFILE=baseline — smoke-уровень + короткий пик 100 VU (подтверждение
 *   очереди artisan serve: сравнить Server-Timing t_tx/t_notify vs http duration).
 *
 * Env:
 *   BASE_URL     — по умолчанию http://localhost:8083
 *   TOKENS_FILE  — JSON с Sanctum-токенами (один на VU).
 *                  По умолчанию ../tokens.json (open() в k6 — относительно скрипта).
 *   PROFILE      — deep (default) | smoke | baseline
 *
 * Формат TOKENS_FILE (любой из вариантов):
 *   ["plain-token", ...]
 *   [{ "token": "...", "max_user_id": 900001 }, ...]
 *   { "tokens": [ ...как выше... ] }
 *
 * Для PROFILE=deep желательно ≥105 токенов
 * (100 VU stress + 5 изолированных для competitive;
 * php artisan max:load-test:tokens 105).
 *
 * Перед прогоном, если меню пустое («нет доступных блюд»):
 *   php artisan max:load-test:prepare-menu
 */

const BASE_URL = (__ENV.BASE_URL || 'http://localhost:8083').replace(/\/$/, '');
// k6 open() резолвит относительные пути от каталога скрипта (scripts/), не от CWD.
const TOKENS_FILE = __ENV.TOKENS_FILE || '../tokens.json';
const PROFILE = (__ENV.PROFILE || 'deep').toLowerCase();

const tokens = new SharedArray('load-test-tokens', () => {
  const raw = JSON.parse(open(TOKENS_FILE));
  return normalizeTokens(raw);
});

/** Хвост latency submit — удобно смотреть p(99)/max в summary и Grafana. */
const submitDuration = new Trend('food_submit_duration', true);

/** Прикладные тайминги из Server-Timing (без очереди HTTP-сервера). */
const submitTxDuration = new Trend('food_submit_t_tx', true);
const submitNotifyDuration = new Trend('food_submit_t_notify', true);
const submitAppDuration = new Trend('food_submit_t_submit', true);

const smokeOptions = {
  scenarios: {
    smoke: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '30s', target: 5 },
        { duration: '2m', target: 15 },
        { duration: '30s', target: 0 },
      ],
      gracefulRampDown: '30s',
      exec: 'happyPath',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    'http_req_duration{name:submit}': ['p(95)<1500'],
    checks: ['rate>0.99'],
  },
};

const deepOptions = {
  scenarios: {
    /**
     * Stress + soak: рост до 50/100 VU, затем удержание ~15 мин.
     * Смотреть корреляцию latency с ramp в Grafana (Influx).
     */
    stress_soak: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '1m', target: 15 },
        { duration: '2m', target: 50 },
        { duration: '2m', target: 100 },
        { duration: '15m', target: 100 },
        { duration: '1m', target: 0 },
      ],
      gracefulRampDown: '30s',
      exec: 'happyPath',
    },
    /**
     * Конкуренция: 10 VU на хвосте пула (последние 5 токенов),
     * изолированно от stress (VU 1–100 берут начало списка).
     * Вместе со stress 100 + negative 5 даёт пик ≈115 VU (local deep headroom).
     * Привязка ресторана та же (tokenIndex % R). Старт после прогрева stress_soak.
     */
    competitive: {
      executor: 'constant-vus',
      vus: 10,
      duration: '12m',
      startTime: '5m',
      gracefulStop: '30s',
      exec: 'competitivePath',
    },
    /**
     * Негатив: заведомо невалидные dish_id / пустой адрес.
     * Отдельные tags — не смешивать с happy-path failed rate без фильтра.
     */
    negative: {
      executor: 'constant-vus',
      vus: 5,
      duration: '12m',
      startTime: '5m',
      gracefulStop: '30s',
      exec: 'negativePath',
    },
  },
  thresholds: {
    // Ошибки только happy-path / stress (negative: expectedStatuses 4xx → не в failed).
    // competitive: без http_req_failed — гонки закрыты checks + expectedStatuses на submit.
    'http_req_failed{scenario:stress_soak}': ['rate<0.01'],
    // Latency SLO только для stress_soak: competitive (гонки/422) не смешивает p99.
    'http_req_duration{name:submit,scenario:stress_soak}': ['p(95)<1500', 'p(99)<3000'],
    'food_submit_duration{scenario:stress_soak}': ['p(95)<1500', 'p(99)<3000'],
    'checks{scenario:stress_soak}': ['rate>0.99'],
    // competitive: 201 или ожидаемый 422 «Корзина пуста».
    'checks{scenario:competitive}': ['rate>0.95'],
    // Негатив обязан получать 4xx, не 2xx.
    'checks{scenario:negative}': ['rate>0.95'],
  },
};

/**
 * Короткий контрольный прогон для профиля latency:
 * сначала ~15 VU, затем ramp до 100 VU на 1–2 мин пика.
 * Пороги latency намеренно мягкие — цель измерить, а не «пройти SLO».
 */
const baselineOptions = {
  scenarios: {
    baseline_smoke: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '20s', target: 15 },
        { duration: '1m', target: 15 },
        { duration: '20s', target: 0 },
      ],
      gracefulRampDown: '20s',
      exec: 'happyPath',
    },
    baseline_100: {
      executor: 'ramping-vus',
      startVUs: 0,
      startTime: '2m',
      stages: [
        { duration: '30s', target: 50 },
        { duration: '30s', target: 100 },
        { duration: '2m', target: 100 },
        { duration: '30s', target: 0 },
      ],
      gracefulRampDown: '20s',
      exec: 'happyPath',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.05'],
    'checks{scenario:baseline_smoke}': ['rate>0.95'],
    'checks{scenario:baseline_100}': ['rate>0.90'],
  },
};

export const options =
  PROFILE === 'smoke' ? smokeOptions : PROFILE === 'baseline' ? baselineOptions : deepOptions;

/**
 * @param {unknown} raw
 * @returns {string[]}
 */
function normalizeTokens(raw) {
  const list = Array.isArray(raw) ? raw : raw && Array.isArray(raw.tokens) ? raw.tokens : null;

  if (!list || list.length === 0) {
    throw new Error(`Файл токенов пуст или неверный формат: ${TOKENS_FILE}`);
  }

  return list.map((entry, index) => {
    if (typeof entry === 'string' && entry.length > 0) {
      return entry;
    }
    if (entry && typeof entry.token === 'string' && entry.token.length > 0) {
      return entry.token;
    }
    throw new Error(`Некорректный токен по индексу ${index} в ${TOKENS_FILE}`);
  });
}

/**
 * @param {string} path
 * @returns {string}
 */
function apiUrl(path) {
  return `${BASE_URL}/api${path}`;
}

/**
 * @param {string} token
 * @returns {Record<string, string>}
 */
function authHeaders(token) {
  return {
    Authorization: `Bearer ${token}`,
    Accept: 'application/json',
    'Content-Type': 'application/json',
  };
}

/**
 * @param {object} menu
 * @returns {number|null}
 */
function pickDishId(menu) {
  const categories = menu && Array.isArray(menu.categories) ? menu.categories : [];
  for (const category of categories) {
    const dishes = category && Array.isArray(category.dishes) ? category.dishes : [];
    for (const dish of dishes) {
      if (dish && typeof dish.id === 'number') {
        return dish.id;
      }
    }
  }
  return null;
}

/**
 * Выбор токена для VU с индексом, совпадающим с фактическим токеном.
 * Без poolSize (stress / smoke / negative / baseline):
 *   tokenIndex = (vu - 1) % count — начало массива.
 * С poolSize (competitive): хвост массива —
 *   offset = count - size, tokenIndex = offset + ((vu - 1) % size).
 * При count <= poolSize — весь пул (offset=0), безопасная деградация.
 *
 * @param {number} count
 * @param {number} [poolSize]
 * @returns {{ token: string, tokenIndex: number }}
 */
function resolveToken(count, poolSize) {
  if (poolSize == null) {
    const tokenIndex = (vu.idInTest - 1) % Math.max(1, count);
    return { token: tokens[tokenIndex], tokenIndex };
  }

  const size = Math.max(1, Math.min(poolSize, count));
  const offset = count - size;
  const tokenIndex = offset + ((vu.idInTest - 1) % size);
  return { token: tokens[tokenIndex], tokenIndex };
}

/**
 * @param {string|undefined|null} header
 * @returns {Record<string, number>}
 */
function parseServerTiming(header) {
  /** @type {Record<string, number>} */
  const out = {};
  if (!header) {
    return out;
  }

  const parts = String(header).split(',');
  for (let i = 0; i < parts.length; i++) {
    const match = parts[i].trim().match(/^([^;]+);(?:\s*)dur=([0-9.]+)/i);
    if (match) {
      out[match[1]] = parseFloat(match[2]);
    }
  }

  return out;
}

/**
 * @param {import('k6/http').RefinedResponse<any>} submitRes
 */
function recordSubmitServerTiming(submitRes) {
  const timing = parseServerTiming(submitRes.headers['Server-Timing'] || submitRes.headers['server-timing']);
  if (typeof timing.t_tx === 'number') {
    submitTxDuration.add(timing.t_tx);
  }
  if (typeof timing.t_notify === 'number') {
    submitNotifyDuration.add(timing.t_notify);
  }
  if (typeof timing.t_submit === 'number') {
    submitAppDuration.add(timing.t_submit);
  }
}

/**
 * Ожидаемая гонка competitive: 422 с message «Корзина пуста.»
 * Парсим JSON — Laravel без JSON_UNESCAPED_UNICODE отдаёт \uXXXX в raw body.
 *
 * @param {import('k6/http').RefinedResponse<any>} r
 * @returns {boolean}
 */
function isEmptyCartRaceResponse(r) {
  if (r.status !== 422) {
    return false;
  }
  try {
    const body = r.json();
    const message = body && typeof body.message === 'string' ? body.message : '';
    return message.includes('Корзина пуста');
  } catch (e) {
    return false;
  }
}

export function setup() {
  if (tokens.length === 0) {
    throw new Error('Нет токенов для VU. Сгенерируйте tokens.json (max:load-test:tokens).');
  }

  if (PROFILE === 'deep' && tokens.length < 105) {
    console.warn(
      `food_order_flow: tokens=${tokens.length} < 105 — для deep нужно ≥105 (100 VU stress + 5 изолированных competitive). Рекомендуется: max:load-test:tokens 105`,
    );
  }

  if (PROFILE === 'baseline' && tokens.length < 100) {
    console.warn(
      `food_order_flow: PROFILE=baseline, tokens=${tokens.length} < 100 — пик 100 VU будет переиспользовать токены.`,
    );
  }

  console.log(`food_order_flow: PROFILE=${PROFILE}, BASE_URL=${BASE_URL}, tokens=${tokens.length}`);
  return { tokenCount: tokens.length };
}

/**
 * Happy-path: рестораны → меню → корзина → адрес → submit.
 * Ресторан жёстко привязан к user: restaurants[tokenIndex % R].
 *
 * @param {{ tokenCount: number }} data
 * @param {{ tokenPoolSize?: number|null, allowEmptyCartRace?: boolean }} [opts]
 */
function runOrderFlow(data, opts = {}) {
  const tokenPoolSize = opts.tokenPoolSize != null ? opts.tokenPoolSize : null;
  const allowEmptyCartRace = opts.allowEmptyCartRace === true;
  const { token, tokenIndex } = resolveToken(
    data.tokenCount,
    tokenPoolSize != null ? tokenPoolSize : undefined,
  );
  const headers = authHeaders(token);
  const vuLabel = vu.idInTest;

  const restaurantsRes = http.get(apiUrl('/food/restaurants'), {
    headers,
    tags: { name: 'restaurants' },
  });

  const restaurantsOk = check(restaurantsRes, {
    'restaurants status 200': (r) => r.status === 200,
  });

  if (!restaurantsOk) {
    console.error(`[VU ${vuLabel}] restaurants failed: ${restaurantsRes.status}`);
    sleep(randomThinkTime());
    return;
  }

  let restaurantsBody;
  try {
    restaurantsBody = restaurantsRes.json();
  } catch (e) {
    console.error(`[VU ${vuLabel}] restaurants JSON parse error`);
    sleep(randomThinkTime());
    return;
  }

  const restaurants = restaurantsBody.restaurants || [];
  if (restaurants.length === 0) {
    console.error(`[VU ${vuLabel}] нет ресторанов`);
    sleep(randomThinkTime());
    return;
  }

  // Жёсткая привязка user→restaurant, но при пустом меню (is_available=false /
  // нет offsets на weekday) пробуем остальные активные рестораны по кругу.
  const startIndex = tokenIndex % restaurants.length;
  let restaurantId = null;
  let dishId = null;

  for (let offset = 0; offset < restaurants.length; offset++) {
    const restaurant = restaurants[(startIndex + offset) % restaurants.length];
    const candidateId = restaurant.id;

    const menuRes = http.get(apiUrl(`/food/restaurants/${candidateId}/menu`), {
      headers,
      tags: { name: 'menu' },
    });

    const menuOk = check(menuRes, {
      'menu status 200': (r) => r.status === 200,
    });

    if (!menuOk) {
      console.error(`[VU ${vuLabel}] menu failed: ${menuRes.status} restaurant=${candidateId}`);
      continue;
    }

    let candidateDishId = null;
    try {
      const menuBody = menuRes.json();
      candidateDishId = pickDishId(menuBody.menu);
    } catch (e) {
      console.error(`[VU ${vuLabel}] menu JSON parse error restaurant=${candidateId}`);
      continue;
    }

    if (candidateDishId !== null) {
      restaurantId = candidateId;
      dishId = candidateDishId;
      break;
    }
  }

  if (dishId === null || restaurantId === null) {
    console.error(
      `[VU ${vuLabel}] нет доступных блюд (рестораны: ${restaurants.map((r) => r.id).join(',')}). ` +
        'Перед прогоном: docker compose exec -T service-c php artisan max:load-test:prepare-menu',
    );
    sleep(randomThinkTime());
    return;
  }

  const cartAddRes = http.post(
    apiUrl('/food/cart/items'),
    JSON.stringify({ dish_id: dishId, quantity: 1 }),
    {
      headers,
      tags: { name: 'cart_add' },
    },
  );

  const cartAddOk = check(cartAddRes, {
    'cart_add status 2xx': (r) => r.status >= 200 && r.status < 300,
  });

  if (!cartAddOk) {
    console.error(`[VU ${vuLabel}] cart_add failed: ${cartAddRes.status} dish=${dishId}`);
    sleep(randomThinkTime());
    return;
  }

  const address = `Load test street, VU ${vuLabel}`;
  const cartAddressRes = http.patch(
    apiUrl('/food/cart'),
    JSON.stringify({ delivery_address: address }),
    {
      headers,
      tags: { name: 'cart_address' },
    },
  );

  const cartAddressOk = check(cartAddressRes, {
    'cart_address status 200': (r) => r.status === 200,
  });

  if (!cartAddressOk) {
    console.error(`[VU ${vuLabel}] cart_address failed: ${cartAddressRes.status}`);
    sleep(randomThinkTime());
    return;
  }

  /** @type {import('k6/http').Params} */
  const submitParams = {
    headers,
    tags: { name: 'submit' },
  };
  if (allowEmptyCartRace) {
    // Ожидаемая гонка (422 «Корзина пуста») не должна попадать в http_req_failed.
    submitParams.responseCallback = http.expectedStatuses(201, 422);
  }

  const submitRes = http.post(apiUrl('/food/orders/submit'), null, submitParams);

  submitDuration.add(submitRes.timings.duration);
  recordSubmitServerTiming(submitRes);

  const submitOk = allowEmptyCartRace
    ? check(submitRes, {
        'submit status 201 or empty-cart 422': (r) =>
          r.status === 201 || isEmptyCartRaceResponse(r),
      })
    : check(submitRes, {
        'submit status 201': (r) => r.status === 201,
      });

  if (!submitOk) {
    console.error(
      `[VU ${vuLabel}] submit failed: ${submitRes.status} body=${String(submitRes.body).slice(0, 200)}`,
    );
  }

  sleep(randomThinkTime());
}

/**
 * Основной happy-path (stress_soak / smoke).
 * @param {{ tokenCount: number }} data
 */
export function happyPath(data) {
  runOrderFlow(data);
}

/**
 * Конкурентный поток: хвост пула (последние 5 токенов), изолирован от stress;
 * ресторан закреплён за user (tokenIndex % R) — гонки за одну корзину на user.
 * @param {{ tokenCount: number }} data
 */
export function competitivePath(data) {
  runOrderFlow(data, { tokenPoolSize: 5, allowEmptyCartRace: true });
}

/**
 * Ожидаемые клиентские ошибки (400–499) для negative —
 * не учитывать в http_req_failed.
 */
const expectClientError = http.expectedStatuses(
  ...Array.from({ length: 100 }, (_, i) => 400 + i),
);

/**
 * Негативные запросы: невалидный dish_id и пустой адрес.
 * Ожидаем 4xx, не 5xx / не 2xx.
 * @param {{ tokenCount: number }} data
 */
export function negativePath(data) {
  const { token } = resolveToken(data.tokenCount);
  const headers = authHeaders(token);
  const vuLabel = vu.idInTest;

  const badCartRes = http.post(
    apiUrl('/food/cart/items'),
    JSON.stringify({ dish_id: -1, quantity: 1 }),
    {
      headers,
      tags: { name: 'negative_cart_add' },
      responseCallback: expectClientError,
    },
  );

  check(badCartRes, {
    'negative cart_add is 4xx': (r) => r.status >= 400 && r.status < 500,
  });

  if (badCartRes.status >= 500) {
    console.error(`[VU ${vuLabel}] negative cart_add unexpected 5xx: ${badCartRes.status}`);
  }

  const badAddressRes = http.patch(
    apiUrl('/food/cart'),
    JSON.stringify({ delivery_address: '' }),
    {
      headers,
      tags: { name: 'negative_cart_address' },
      responseCallback: expectClientError,
    },
  );

  check(badAddressRes, {
    'negative cart_address is 4xx': (r) => r.status >= 400 && r.status < 500,
  });

  if (badAddressRes.status >= 500) {
    console.error(`[VU ${vuLabel}] negative cart_address unexpected 5xx: ${badAddressRes.status}`);
  }

  sleep(randomThinkTime());
}

/**
 * Пауза между итерациями: 0.5–2 с.
 * @returns {number}
 */
function randomThinkTime() {
  return Math.random() * 1.5 + 0.5;
}
