<script setup>
/**
 * Раздел лички с пользователем MAX через бота (роль max_manager).
 */
import { onMounted, onUnmounted } from 'vue';
import { bindBackButton, closeMaxApp, getPlatform, hideBackButton } from '../../bridge/maxBridge';
import { useAdminChrome } from '../../composables/useAdminChrome';
import BotChatPage from '../../pages/admin/BotChatPage.vue';

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
    <BotChatPage class="min-h-0 flex-1" />
</template>
