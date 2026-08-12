/**
 * Axios-инстанс, Bearer-токен и разбор ошибок API.
 * 401 → повторная авторизация через handler из auth.js (без циклического импорта).
 */
import axios from 'axios';
import { getInitData } from '../bridge/maxBridge';

/** @type {string|null} Токен авторизации после POST /max/auth */
let authToken = sessionStorage.getItem('max_miniapp_token');

/** @type {Promise<unknown>|null} */
let reauthPromise = null;

/** @type {((initData: string) => Promise<unknown>)|null} */
let authenticateHandler = null;

/**
 * Регистрирует authenticate из auth.js (вызывается при загрузке модуля auth).
 *
 * @param {(initData: string) => Promise<unknown>} fn
 */
export function registerAuthenticateHandler(fn) {
    authenticateHandler = fn;
}

export const client = axios.create({
    baseURL: '/api',
    headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
    },
});

client.interceptors.request.use((config) => {
    if (!authToken) {
        authToken = sessionStorage.getItem('max_miniapp_token');
    }

    // Подставляем Bearer после authenticate(); без токена — только публичные эндпоинты
    if (authToken) {
        config.headers.Authorization = `Bearer ${authToken}`;
    }

    if (config.data instanceof FormData) {
        // Дефолт application/json ломает multipart: Laravel не видит поле photo
        delete config.headers['Content-Type'];
    }

    return config;
});

client.interceptors.response.use(
    (response) => response,
    async (error) => {
        const config = error.config;

        if (
            !axios.isAxiosError(error)
            || error.response?.status !== 401
            || !config
            || config.url === '/max/auth'
            || config.__isAuthRetry
        ) {
            return Promise.reject(error);
        }

        config.__isAuthRetry = true;

        if (!reauthPromise) {
            reauthPromise = reauthenticateFromBridge().finally(() => {
                reauthPromise = null;
            });
        }

        try {
            await reauthPromise;
        } catch (reauthError) {
            return Promise.reject(reauthError);
        }

        return client(config);
    },
);

/**
 * Повторная авторизация по initData из MAX Bridge (после 401).
 */
async function reauthenticateFromBridge() {
    const initData = getInitData();

    if (!initData) {
        clearAuthToken();
        throw new Error('Сессия истекла. Перезапустите mini-app в MAX.');
    }

    if (typeof authenticateHandler !== 'function') {
        clearAuthToken();
        throw new Error('Сессия истекла. Перезапустите mini-app в MAX.');
    }

    await authenticateHandler(initData);
}

/**
 * @param {string} token
 */
export function setAuthToken(token) {
    authToken = token;
    sessionStorage.setItem('max_miniapp_token', token);
}

/**
 * Очищает сохранённый Bearer token mini-app.
 */
export function clearAuthToken() {
    authToken = null;
    sessionStorage.removeItem('max_miniapp_token');
}

/**
 * Понятные русские тексты для HTTP-статусов без тела ответа (nginx/axios).
 *
 * @type {Record<number, string>}
 */
const HTTP_STATUS_MESSAGES_RU = {
    413: 'Файл слишком большой. Уменьшите размер изображения и попробуйте снова.',
};

/**
 * @param {unknown} error
 * @returns {string}
 */
export function extractErrorMessage(error) {
    if (axios.isAxiosError(error)) {
        const validationMessage = extractFirstValidationError(error);

        if (validationMessage) {
            return validationMessage;
        }

        const apiMessage = error.response?.data?.message;

        if (typeof apiMessage === 'string' && apiMessage.trim() !== '') {
            return apiMessage;
        }

        const status = error.response?.status;
        const statusMessage = typeof status === 'number'
            ? HTTP_STATUS_MESSAGES_RU[status]
            : undefined;

        if (statusMessage) {
            return statusMessage;
        }

        if (typeof status === 'number') {
            return `Ошибка запроса (код ${status}).`;
        }

        return error.message;
    }

    if (error instanceof Error) {
        return error.message;
    }

    return 'Произошла ошибка';
}

/**
 * @param {import('axios').AxiosError} error
 * @returns {Record<string, string>}
 */
export function extractValidationErrors(error) {
    if (!axios.isAxiosError(error) || error.response?.status !== 422) {
        return {};
    }

    const errors = error.response.data?.errors;

    if (!errors || typeof errors !== 'object') {
        return {};
    }

    /** @type {Record<string, string>} */
    const result = {};

    for (const [field, messages] of Object.entries(errors)) {
        if (Array.isArray(messages) && messages.length > 0 && typeof messages[0] === 'string') {
            result[field] = messages[0];
        }
    }

    return result;
}

/**
 * @param {import('axios').AxiosError} error
 * @returns {string|null}
 */
function extractFirstValidationError(error) {
    const validationErrors = extractValidationErrors(error);
    const firstMessage = Object.values(validationErrors)[0];

    return firstMessage ?? null;
}
