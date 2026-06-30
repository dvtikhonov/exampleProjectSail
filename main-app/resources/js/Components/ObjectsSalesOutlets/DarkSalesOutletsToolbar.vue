<script setup>
defineProps({
    hasActiveFilters: {
        type: Boolean,
        default: false,
    },
    showReports: {
        type: Boolean,
        default: true,
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
    isMailing: {
        type: Boolean,
        default: false,
    },
    mailButtonText: {
        type: String,
        default: 'Отправить по почте',
    },
    mailStatusText: {
        type: String,
        default: '',
    },
    isMaxSending: {
        type: Boolean,
        default: false,
    },
    maxButtonText: {
        type: String,
        default: 'Отправить в MAX',
    },
    maxStatusText: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['open-columns', 'open-filters', 'save-file', 'send-mail', 'send-max']);
</script>

<template>
    <div class="flex flex-col gap-3 border-b border-slate-800 bg-slate-900/95 p-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="text-sm font-medium text-slate-200">
                Управление таблицей объектов продаж
            </div>
            <div class="mt-1 text-xs text-slate-500">
                <template v-if="showReports">
                    Настройки колонок, фильтры, экспорт, почта и отправка в MAX.
                </template>
                <template v-else>
                    Настройки колонок и фильтры.
                </template>
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
            <div
                v-if="showReports"
                class="flex flex-col gap-1"
            >
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
            <div
                v-if="showReports"
                class="flex flex-col gap-1"
            >
                <button
                    type="button"
                    class="inline-flex items-center rounded-lg border border-cyan-500/60 bg-slate-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-cyan-300 transition hover:border-cyan-400 hover:bg-slate-700 hover:text-cyan-200 focus:outline-none focus:ring-2 focus:ring-cyan-400 focus:ring-offset-2 focus:ring-offset-slate-900 disabled:cursor-not-allowed disabled:border-slate-700 disabled:bg-slate-800 disabled:text-slate-500"
                    :disabled="isMailing"
                    @click="emit('send-mail')"
                >
                    {{ mailButtonText }}
                </button>
                <span
                    v-if="mailStatusText"
                    class="text-xs text-slate-400"
                >
                    {{ mailStatusText }}
                </span>
            </div>
            <div
                v-if="showReports"
                class="flex flex-col gap-1"
            >
                <button
                    type="button"
                    class="inline-flex items-center rounded-lg border border-violet-500/60 bg-slate-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-violet-300 transition hover:border-violet-400 hover:bg-slate-700 hover:text-violet-200 focus:outline-none focus:ring-2 focus:ring-violet-400 focus:ring-offset-2 focus:ring-offset-slate-900 disabled:cursor-not-allowed disabled:border-slate-700 disabled:bg-slate-800 disabled:text-slate-500"
                    :disabled="isMaxSending"
                    @click="emit('send-max')"
                >
                    {{ maxButtonText }}
                </button>
                <span
                    v-if="maxStatusText"
                    class="text-xs text-slate-400"
                >
                    {{ maxStatusText }}
                </span>
            </div>
        </div>
    </div>
</template>
