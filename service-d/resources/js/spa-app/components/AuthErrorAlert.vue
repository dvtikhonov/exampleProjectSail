<script setup>
/** Блок ошибки API/аутентификации; скрыт, если message пустой. */
defineProps({
    message: {
        type: [String, null],
        default: null,
    },
});
</script>

<template>
    <div
        v-if="message"
        class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
        role="alert"
    >
        {{ message }}
    </div>
</template>
