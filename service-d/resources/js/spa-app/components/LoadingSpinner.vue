<script setup>
/** Центрированный спиннер с подписью (загрузка данных, редиректы). */
defineProps({
    label: {
        type: String,
        default: 'Загрузка…',
    },
});
</script>

<template>
    <div class="flex flex-col items-center gap-3 text-slate-600">
        <span
            class="inline-block h-8 w-8 animate-spin rounded-full border-2 border-slate-300 border-t-sky-600"
            aria-hidden="true"
        />
        <span class="text-sm">{{ label }}</span>
    </div>
</template>
