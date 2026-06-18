<script setup>
/**
 * Пустое состояние списка отзывов.
 *
 * isSyncInProgress — первая синхронизация ещё идёт (polling на странице Reviews).
 */
defineProps({
    isSyncInProgress: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div class="mt-10 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center">
        <p class="text-sm text-slate-600">
            <template v-if="isSyncInProgress">
                Отзывы ещё загружаются. Страница обновится автоматически после синхронизации.
            </template>
            <template v-else>
                Отзывов пока нет. Попробуйте обновить данные в настройках.
            </template>
        </p>
    </div>
</template>
