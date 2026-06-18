/**
 * Точка входа SPA service-d: Vue 3 + Vue Router.
 *
 * Маршруты защищены cookie-сессией Sanctum (см. useAuth, api/client).
 * meta.requiresAuth — только для авторизованных; meta.guest — только для гостей.
 */
import '../../css/app.css';
import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';
import HomeRedirect from './pages/HomeRedirect.vue';
import Login from './pages/Login.vue';
import Reviews from './pages/Reviews.vue';
import Settings from './pages/Settings.vue';
import { useAuth } from './composables/useAuth';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/login', name: 'login', component: Login, meta: { guest: true } },
        { path: '/', name: 'home', component: HomeRedirect, meta: { requiresAuth: true } },
        { path: '/settings/:organizationId?', name: 'settings', component: Settings, meta: { requiresAuth: true } },
        {
            path: '/reviews/:organizationId',
            name: 'reviews',
            component: Reviews,
            meta: { requiresAuth: true },
        },
        { path: '/:pathMatch(.*)*', redirect: { name: 'home' } },
    ],
});

// Guard: один раз проверяем GET /api/user, затем пускаем или редиректим.
router.beforeEach(async (to) => {
    const { user, fetchUser, hasCheckedSession } = useAuth();

    if (!hasCheckedSession.value) {
        await fetchUser();
    }

    if (to.meta.requiresAuth && !user.value) {
        return { name: 'login' };
    }

    if (to.meta.guest && user.value) {
        return { name: 'home' };
    }

    return true;
});

createApp(App).use(router).mount('#spa-app');
