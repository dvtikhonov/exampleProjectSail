/**
 * Централизованная обработка HTTP 401: сброс сессии и редирект на /login.
 */
export function useUnauthorizedHandler() {
    /** Запросы, при 401 на которых редирект не нужен (форма входа, CSRF). */
    function shouldIgnoreUnauthorized(url: string): boolean {
        return url.includes('/auth/login')
            || url.includes('/auth/register')
            || url.includes('/sanctum/csrf-cookie')
            || url.includes('/api/user');
    }

    /**
     * Сбрасывает пользователя и перенаправляет на страницу входа.
     */
    function handleUnauthorized(requestUrl: string): void {
        if (import.meta.server) {
            return;
        }

        const url = String(requestUrl);

        if (shouldIgnoreUnauthorized(url)) {
            return;
        }

        const { user, hasCheckedSession } = useAuth();
        user.value = null;
        hasCheckedSession.value = true;

        const router = useRouter();
        const currentPath = router.currentRoute.value.path;

        if (currentPath !== '/login' && currentPath !== '/register') {
            void router.push('/login');
        }
    }

    return {
        shouldIgnoreUnauthorized,
        handleUnauthorized,
    };
}
