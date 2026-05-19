<script setup>
defineProps({
    pagination: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['change-page']);

const pageNumbers = (pagination) => {
    const current = pagination.current_page;
    const last = pagination.last_page;
    const start = Math.max(current - 2, 1);
    const end = Math.min(start + 4, last);

    return Array.from({ length: end - start + 1 }, (_, index) => start + index);
};
</script>

<template>
    <div class="flex flex-col gap-3 border-t border-slate-800 bg-slate-900/95 px-4 py-3 text-sm text-slate-400 sm:flex-row sm:items-center sm:justify-between">
        <div>
            Всего: <span class="font-semibold text-slate-100">{{ pagination.total }}</span>
            <span v-if="pagination.total > 0">
                , показаны {{ pagination.from }}-{{ pagination.to }}
            </span>
        </div>

        <div class="flex items-center gap-2">
            <button
                type="button"
                class="inline-flex items-center rounded-lg border border-slate-700 bg-slate-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-100 shadow-sm transition hover:border-cyan-400 disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="pagination.current_page === 1"
                @click="emit('change-page', pagination.current_page - 1)"
            >
                Назад
            </button>

            <button
                v-for="page in pageNumbers(pagination)"
                :key="page"
                type="button"
                class="rounded-lg border px-3 py-2 text-sm font-medium transition"
                :class="page === pagination.current_page
                    ? 'border-cyan-400 bg-cyan-400 text-slate-950'
                    : 'border-slate-700 bg-slate-800 text-slate-200 hover:border-cyan-400'"
                @click="emit('change-page', page)"
            >
                {{ page }}
            </button>

            <button
                type="button"
                class="inline-flex items-center rounded-lg border border-slate-700 bg-slate-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-100 shadow-sm transition hover:border-cyan-400 disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="pagination.current_page === pagination.last_page"
                @click="emit('change-page', pagination.current_page + 1)"
            >
                Вперед
            </button>
        </div>
    </div>
</template>
