<script setup>
/** Предупреждение: отображаются кэшированные отзывы, идёт фоновое обновление. */
defineProps({
    message: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <div
        class="mt-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
        role="status"
    >
        {{ message }}
    </div>
</template>
