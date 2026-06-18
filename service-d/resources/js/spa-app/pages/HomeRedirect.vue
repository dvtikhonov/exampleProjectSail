<script setup>
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import LoadingSpinner from '../components/LoadingSpinner.vue';

const router = useRouter();

onMounted(async () => {
    await router.replace({ name: 'settings' });
});
</script>

<template>
    <div class="flex min-h-dvh items-center justify-center">
        <LoadingSpinner label="Загрузка…" />
    </div>
</template>
