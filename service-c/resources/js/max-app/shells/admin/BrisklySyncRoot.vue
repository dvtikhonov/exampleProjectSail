<script setup>
/**
 * Раздел синхронизации Briskly (роль max_manager).
 */
import { onMounted, onUnmounted } from 'vue';
import { bindBackButton, closeMaxApp, getPlatform, hideBackButton } from '../../bridge/maxBridge';
import { useAdminChrome } from '../../composables/useAdminChrome';
import AdminBrisklySyncPage from '../../pages/admin/AdminBrisklySyncPage.vue';

const { sectionNavVisible } = useAdminChrome();

/** @type {() => void} */
let unbindBackButton = () => {};

onMounted(() => {
    sectionNavVisible.value = true;
    unbindBackButton();

    if (getPlatform() === 'desktop') {
        unbindBackButton = bindBackButton(closeMaxApp);
    } else {
        hideBackButton();
    }
});

onUnmounted(() => {
    unbindBackButton();
    sectionNavVisible.value = true;
});
</script>

<template>
    <AdminBrisklySyncPage class="min-h-0 flex-1" />
</template>
