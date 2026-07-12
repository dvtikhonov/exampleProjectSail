<script setup lang="ts">
const { user, fetchUser, hasCheckedSession } = useAuth();

if (import.meta.client) {
    if (!hasCheckedSession.value) {
        await fetchUser();
    }

    await navigateTo(user.value ? '/listtodos' : '/login');
}
</script>

<template>
    <div class="flex min-h-[50vh] items-center justify-center text-slate-400">
        Перенаправление…
    </div>
</template>
