<script setup>
/**
 * Корневой shell mini-app (MAX / Telegram).
 *
 * Auth + выбор режима (client / admin). Telegram — только клиент.
 * Доменная логика — в shells/*.
 */
import { onMounted } from 'vue';
import { disableVerticalSwipes, isTelegramChannel } from './bridge';
import AuthGate from './components/AuthGate.vue';
import { useAuth } from './composables/useAuth';
import AdminAppShell from './shells/AdminAppShell.vue';
import ClientAppShell from './shells/ClientAppShell.vue';

const forceClientOnly = isTelegramChannel();

const {
    authLoading,
    authError,
    hasAdminRoles,
    initAuth,
} = useAuth();

onMounted(async () => {
    await initAuth();

    if (!authError.value) {
        disableVerticalSwipes();
    }
});
</script>

<template>
    <AuthGate
        :loading="authLoading"
        :error="authError"
    >
        <ClientAppShell v-if="forceClientOnly || !hasAdminRoles" />
        <AdminAppShell v-else />
    </AuthGate>
</template>
