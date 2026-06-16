<script setup>
import { RouterView } from 'vue-router';
import { useAuth } from './composables/useAuth';
import LoadingSpinner from './components/LoadingSpinner.vue';

const { isInitializing } = useAuth();
</script>

<template>
    <div class="min-h-dvh">
        <div
            v-if="isInitializing"
            class="flex min-h-dvh items-center justify-center"
        >
            <LoadingSpinner label="Проверка сессии…" />
        </div>
        <RouterView v-else />
    </div>
</template>
