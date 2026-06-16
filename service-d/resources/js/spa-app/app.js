import '../../css/app.css';
import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';
import Login from './pages/Login.vue';
import Splash from './pages/Splash.vue';
import { useAuth } from './composables/useAuth';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/login', name: 'login', component: Login, meta: { guest: true } },
        { path: '/', name: 'splash', component: Splash, meta: { requiresAuth: true } },
        { path: '/:pathMatch(.*)*', redirect: '/' },
    ],
});

router.beforeEach(async (to) => {
    const { user, fetchUser, hasCheckedSession } = useAuth();

    // На /login всегда перепроверяем сессию — иначе устаревший user блокирует вход.
    if (!hasCheckedSession.value || to.name === 'login') {
        await fetchUser();
    }

    if (to.meta.requiresAuth && !user.value) {
        return { name: 'login' };
    }

    if (to.meta.guest && user.value) {
        return { name: 'splash' };
    }

    return true;
});

createApp(App).use(router).mount('#spa-app');
