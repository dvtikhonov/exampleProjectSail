<script setup lang="ts">
await navigateTo('/tasks', { replace: true });
</script>

<template>
    <AppSpinner
        centered
        size="lg"
        label="Перенаправление…"
    />
</template>
