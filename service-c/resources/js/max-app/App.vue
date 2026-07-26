<script setup>
/**
 * Корневой shell MAX mini-app.
 *
 * Auth + выбор режима (client / admin). Доменная логика — в shells/*.
 */
import { onMounted } from 'vue';
import { disableVerticalSwipes } from './bridge/maxBridge';
import AuthGate from './components/AuthGate.vue';
import { useAuth } from './composables/useAuth';
import AdminAppShell from './shells/AdminAppShell.vue';
import ClientAppShell from './shells/ClientAppShell.vue';

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
        <AdminAppShell v-if="hasAdminRoles" />
        <ClientAppShell v-else />
    </AuthGate>
</template>
