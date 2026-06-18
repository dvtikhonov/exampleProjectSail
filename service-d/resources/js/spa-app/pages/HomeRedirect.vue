<script setup>
/**
 * Точка входа после авторизации (маршрут /, name: home).
 *
 * Не рендерит контент — сразу replace на settings (привязка организации).
 * Пока идёт редирект, показывается полноэкранный спиннер.
 */
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import LoadingSpinner from '../components/LoadingSpinner.vue';

const router = useRouter();

onMounted(async () => {
    await router.replace({ name: 'settings' });
});
</script>

<template>
    <!-- Краткий loader на время router.replace → settings -->
    <div class="flex min-h-dvh items-center justify-center">
        <LoadingSpinner label="Загрузка…" />
    </div>
</template>
