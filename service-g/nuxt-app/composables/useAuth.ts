import type {
    AuthUser,
    AuthUserResponse,
    LoginCredentials,
    RegisterPayload,
} from '~/types/auth';

/**
 * Composable аутентификации (Sanctum cookie session).
 *
 * ensureCsrfCookie, fetchUser, login, register и logout — Sanctum cookie session.
 */
export function useAuth() {
    const user = useState<AuthUser | null>('auth-user', () => null);
    const isLoading = useState('auth-loading', () => false);
    const error = useState<string | null>('auth-error', () => null);
    const hasCheckedSession = useState('auth-checked', () => false);

    const { apiFetch, rootFetch, extractErrorMessage } = useApi();

    const isAuthenticated = computed(() => user.value !== null);
    const isInitializing = computed(() => !hasCheckedSession.value);

    /** Сбрасывает текст последней ошибки аутентификации. */
    function resetError(): void {
        error.value = null;
    }

    /** Шаг 1 Sanctum: GET /sanctum/csrf-cookie перед state-changing POST. */
    async function ensureCsrfCookie(): Promise<void> {
        await rootFetch('/sanctum/csrf-cookie');
    }

    /** Проверка сессии: GET /api/user (только на клиенте — Sanctum cookies). */
    async function fetchUser(): Promise<void> {
        if (import.meta.server) {
            hasCheckedSession.value = true;
            return;
        }

        resetError();
        isLoading.value = true;

        try {
            const data = await apiFetch<AuthUserResponse>('/user');
            user.value = data.user ?? null;
        } catch (err: unknown) {
            const status = (err as { status?: number })?.status;

            if (status === 401) {
                user.value = null;
            } else {
                error.value = extractErrorMessage(err, 'Не удалось проверить сессию.');
            }
        } finally {
            isLoading.value = false;
            hasCheckedSession.value = true;
        }
    }

    /**
     * POST /api/login после ensureCsrfCookie().
     */
    async function login(credentials: LoginCredentials): Promise<boolean> {
        resetError();
        isLoading.value = true;

        try {
            await ensureCsrfCookie();
            const data = await apiFetch<AuthUserResponse>('/login', {
                method: 'POST',
                body: credentials,
            });
            user.value = data.user ?? null;

            return true;
        } catch (err: unknown) {
            error.value = extractErrorMessage(err, 'Неверный email или пароль.');

            return false;
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * POST /api/register после ensureCsrfCookie().
     */
    async function register(payload: RegisterPayload): Promise<boolean> {
        resetError();
        isLoading.value = true;

        try {
            await ensureCsrfCookie();
            const data = await apiFetch<AuthUserResponse>('/register', {
                method: 'POST',
                body: payload,
            });
            user.value = data.user ?? null;

            return true;
        } catch (err: unknown) {
            error.value = extractErrorMessage(err, 'Не удалось зарегистрироваться.');

            return false;
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * POST /api/logout (auth:sanctum).
     */
    async function logout(): Promise<boolean> {
        resetError();
        isLoading.value = true;

        try {
            await apiFetch('/logout', { method: 'POST' });
            user.value = null;

            return true;
        } catch (err: unknown) {
            error.value = extractErrorMessage(err, 'Не удалось выйти из системы.');

            return false;
        } finally {
            isLoading.value = false;
        }
    }

    return {
        user,
        isLoading,
        isInitializing,
        hasCheckedSession,
        isAuthenticated,
        error,
        ensureCsrfCookie,
        fetchUser,
        login,
        register,
        logout,
    };
}
