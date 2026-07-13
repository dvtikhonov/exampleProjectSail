import type { FetchOptions } from 'ofetch';
import type { ApiValidationError } from '~/types/auth';

type ApiFetchOptions = Omit<FetchOptions, 'credentials' | 'headers'> & {
    headers?: Record<string, string>;
};

/**
 * HTTP-клиент Nuxt для Laravel Sanctum (cookie session).
 *
 * - credentials: 'include' — session cookies
 * - X-XSRF-TOKEN из cookie XSRF-TOKEN (Laravel Sanctum)
 */
export function useApi() {
    const config = useRuntimeConfig();
    const apiBase = String(config.public.apiBase ?? '');
    const nuxtApp = useNuxtApp();
    const { handleUnauthorized } = useUnauthorizedHandler();

    /** Клиент $fetch с перехватом 401 (из plugins/api-auth.client.ts). */
    function resolveFetchClient(): typeof $fetch {
        return nuxtApp.$sanctumFetch ?? $fetch;
    }

    /**
     * Пробрасывает 401 в централизованный обработчик (fallback, если плагин не подключён).
     */
    function rethrowWithUnauthorizedHandling(error: unknown, requestUrl: string): never {
        const status = (error as { status?: number })?.status;

        if (status === 401) {
            handleUnauthorized(requestUrl);
        }

        throw error;
    }

    /**
     * Читает XSRF-TOKEN из document.cookie (только на клиенте).
     */
    function getXsrfToken(): string | undefined {
        if (import.meta.server) {
            return undefined;
        }

        const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

        if (!match?.[1]) {
            return undefined;
        }

        return decodeURIComponent(match[1]);
    }

    /** Собирает заголовки для Sanctum (Accept, CSRF, X-Requested-With). */
    function buildHeaders(extra?: Record<string, string>): Record<string, string> {
        const headers: Record<string, string> = {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...extra,
        };

        const xsrf = getXsrfToken();

        if (xsrf) {
            headers['X-XSRF-TOKEN'] = xsrf;
        }

        return headers;
    }

    /**
     * Запрос к Laravel API (префикс /api).
     */
    async function apiFetch<T>(path: string, options: ApiFetchOptions = {}): Promise<T> {
        const normalizedPath = path.startsWith('/') ? path : `/${path}`;
        const requestUrl = `${apiBase}/api${normalizedPath}`;

        try {
            return await resolveFetchClient()<T>(requestUrl, {
                ...options,
                credentials: 'include',
                headers: buildHeaders(options.headers),
                timeout: 15_000,
            });
        } catch (error: unknown) {
            rethrowWithUnauthorizedHandling(error, requestUrl);
        }
    }

    /**
     * Запрос вне /api (например GET /sanctum/csrf-cookie).
     */
    async function rootFetch<T>(path: string, options: ApiFetchOptions = {}): Promise<T> {
        const normalizedPath = path.startsWith('/') ? path : `/${path}`;
        const requestUrl = `${apiBase}${normalizedPath}`;

        try {
            return await resolveFetchClient()<T>(requestUrl, {
                ...options,
                credentials: 'include',
                headers: buildHeaders(options.headers),
                timeout: 15_000,
            });
        } catch (error: unknown) {
            rethrowWithUnauthorizedHandling(error, requestUrl);
        }
    }

    /**
     * Извлекает сообщение об ошибке из ответа Laravel.
     */
    function extractErrorMessage(error: unknown, fallback = 'Произошла ошибка. Попробуйте снова.'): string {
        const data = (error as { data?: ApiValidationError })?.data;

        if (data?.message && typeof data.message === 'string') {
            return data.message;
        }

        if (data?.errors && typeof data.errors === 'object') {
            const firstField = Object.keys(data.errors)[0];
            const messages = data.errors[firstField];

            if (Array.isArray(messages) && messages.length > 0) {
                return String(messages[0]);
            }
        }

        return fallback;
    }

    return {
        apiBase,
        apiFetch,
        rootFetch,
        getXsrfToken,
        extractErrorMessage,
    };
}
