<script setup>
/** Карточка одной метрики (лейбл + крупное значение). */
defineProps({
    label: {
        type: String,
        required: true,
    },
    value: {
        type: [String, Number],
        required: true,
    },
});
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-center">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
            {{ label }}
        </p>
        <p class="mt-2 text-3xl font-semibold text-slate-900">
            {{ value }}
        </p>
    </div>
</template>
