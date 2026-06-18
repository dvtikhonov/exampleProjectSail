<script setup>
/**
 * Стартовый экран приложения (приветствие авторизованного пользователя).
 *
 * Показывает карточку с именем пользователя и кнопкой выхода.
 * После успешного logout перенаправляет на страницу входа.
 *
 * Примечание: в текущем роутере вместо Splash используется HomeRedirect → settings.
 */
import { useRouter } from 'vue-router';
import PageShell from '../components/layout/PageShell.vue';
import SplashCard from '../components/splash/SplashCard.vue';
import { useAuth } from '../composables/useAuth';

const router = useRouter();
const { user, logout, isLoading, error } = useAuth();

/** POST /logout и переход на login при успехе. */
async function onLogout() {
    const ok = await logout();

    if (ok) {
        await router.push({ name: 'login' });
    }
}
</script>

<template>
    <!-- Центрированная карточка приветствия -->
    <PageShell
        max-width="lg"
        card-padding="10"
        centered
    >
        <SplashCard
            :user="user"
            :is-loading="isLoading"
            :error="error"
            @logout="onLogout"
        />
    </PageShell>
</template>
