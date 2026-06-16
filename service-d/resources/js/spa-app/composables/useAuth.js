import { computed, ref } from 'vue';
import api from '../api/client';

const user = ref(null);
const isLoading = ref(false);
const error = ref(null);
const hasCheckedSession = ref(false);

/** Полноэкранный loader только до первой проверки GET /api/user. */
const isInitializing = computed(() => !hasCheckedSession.value);

function resetError() {
    error.value = null;
}

function extractErrorMessage(err, fallback = 'Произошла ошибка. Попробуйте снова.') {
    const data = err?.response?.data;

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

async function ensureCsrfCookie() {
    await api.get('/sanctum/csrf-cookie', { baseURL: '' });
}

/**
 * Composable аутентификации SPA (Sanctum cookie session).
 */
export function useAuth() {
    async function fetchUser() {
        resetError();
        isLoading.value = true;

        try {
            const { data } = await api.get('/user');
            user.value = data.user ?? null;
        } catch (err) {
            if (err?.response?.status === 401) {
                user.value = null;
            } else {
                error.value = extractErrorMessage(err, 'Не удалось проверить сессию.');
            }
        } finally {
            isLoading.value = false;
            hasCheckedSession.value = true;
        }
    }

    async function login(credentials) {
        resetError();
        isLoading.value = true;

        try {
            await ensureCsrfCookie();
            await api.post('/login', credentials);
            hasCheckedSession.value = false;
            await fetchUser();

            if (!user.value) {
                error.value = 'Вход выполнен, но сессия не сохранилась. Очистите cookies и попробуйте снова.';
                return false;
            }

            return true;
        } catch (err) {
            error.value = extractErrorMessage(err, 'Неверный email или пароль.');
            return false;
        } finally {
            isLoading.value = false;
        }
    }

    async function register(payload) {
        resetError();
        isLoading.value = true;

        try {
            await ensureCsrfCookie();
            await api.post('/register', payload);
            hasCheckedSession.value = false;
            await fetchUser();

            if (!user.value) {
                error.value = 'Регистрация выполнена, но сессия не сохранилась. Очистите cookies и попробуйте снова.';
                return false;
            }

            return true;
        } catch (err) {
            error.value = extractErrorMessage(err, 'Не удалось зарегистрироваться.');
            return false;
        } finally {
            isLoading.value = false;
        }
    }

    async function logout() {
        resetError();
        isLoading.value = true;

        try {
            await ensureCsrfCookie();
            await api.post('/logout');
        } catch (err) {
            const status = err?.response?.status;

            if (status !== 401 && status !== 419) {
                error.value = extractErrorMessage(err, 'Не удалось связаться с сервером при выходе.');
            }
        } finally {
            user.value = null;
            hasCheckedSession.value = false;
            isLoading.value = false;
        }

        return true;
    }

    function clearAuthState() {
        user.value = null;
        hasCheckedSession.value = false;
        resetError();
    }

    return {
        user,
        isLoading,
        isInitializing,
        hasCheckedSession,
        error,
        fetchUser,
        login,
        register,
        logout,
        clearAuthState,
    };
}
