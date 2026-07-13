<script setup lang="ts">
await navigateTo('/login?register=1');
</script>

<template>
    <AppSpinner
        centered
        size="lg"
        label="Перенаправление…"
    />
</template>
