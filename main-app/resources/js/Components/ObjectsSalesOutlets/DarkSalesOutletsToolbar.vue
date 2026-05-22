<script setup>
defineProps({
    hasActiveFilters: {
        type: Boolean,
        default: false,
    },
    isExporting: {
        type: Boolean,
        default: false,
    },
    exportButtonText: {
        type: String,
        default: 'Сохранить в файл',
    },
    exportStatusText: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['open-columns', 'open-filters', 'save-file']);
</script>

<template>
    <div class="flex flex-col gap-3 border-b border-slate-800 bg-slate-900/95 p-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="text-sm font-medium text-slate-200">
                Управление таблицей объектов продаж
            </div>
            <div class="mt-1 text-xs text-slate-500">
                Настройки колонок, фильтры и сохранение данных в файл.
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                class="inline-flex items-center rounded-lg border border-slate-700 bg-slate-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-100 transition hover:border-cyan-400 hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-cyan-400 focus:ring-offset-2 focus:ring-offset-slate-900"
                @click="emit('open-columns')"
            >
                Настройка таблицы
            </button>
            <button
                type="button"
                class="inline-flex items-center rounded-lg border border-slate-700 bg-slate-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-100 transition hover:border-cyan-400 hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-cyan-400 focus:ring-offset-2 focus:ring-offset-slate-900"
                @click="emit('open-filters')"
            >
                Фильтры
                <span
                    v-if="hasActiveFilters"
                    class="ml-2 rounded-full bg-cyan-400 px-1.5 py-0.5 text-[10px] font-bold text-slate-950"
                >
                    ON
                </span>
            </button>
            <div class="flex flex-col gap-1">
                <button
                    type="button"
                    class="inline-flex items-center rounded-lg border border-transparent bg-cyan-500 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-950 transition hover:bg-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-400 focus:ring-offset-2 focus:ring-offset-slate-900 disabled:cursor-not-allowed disabled:bg-slate-600 disabled:text-slate-300"
                    :disabled="isExporting"
                    @click="emit('save-file')"
                >
                    {{ exportButtonText }}
                </button>
                <span
                    v-if="exportStatusText"
                    class="text-xs text-slate-400"
                >
                    {{ exportStatusText }}
                </span>
            </div>
        </div>
    </div>
</template>
