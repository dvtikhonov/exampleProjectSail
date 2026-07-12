/**
 * Защита маршрутов: неавторизованных пользователей перенаправляет на /login.
 */
export default defineNuxtRouteMiddleware(async (to) => {
    // Пропускаем SSR и публичные страницы auth без проверки сессии.
    if (import.meta.server || to.path === '/login' || to.path === '/register') {
        return;
    }

    const { user, fetchUser, hasCheckedSession } = useAuth();

    if (!hasCheckedSession.value) {
        await fetchUser();
    }

    if (!user.value) {
        return navigateTo('/login');
    }
});
