<script setup>
import { REPORT_JOB_TYPES, reportTypeLabel } from '@/Composables/useReportJobStats';

defineProps({
    statsByType: {
        type: Object,
        default: null,
    },
    isLoading: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
});

const reportTypes = REPORT_JOB_TYPES;
</script>

<template>
    <div
        v-if="isLoading || error || statsByType"
        class="flex flex-col gap-2"
    >
        <div class="text-[10px] font-semibold uppercase tracking-[0.25em] text-slate-500">
            Очередь отчётов
        </div>

        <div
            v-if="isLoading && !statsByType"
            class="text-xs text-slate-500"
        >
            Загрузка счётчиков...
        </div>

        <div
            v-else-if="error"
            class="text-xs text-rose-400"
        >
            {{ error }}
        </div>

        <div
            v-else-if="statsByType"
            class="flex flex-wrap gap-2"
        >
            <div
                v-for="reportType in reportTypes"
                :key="reportType"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-700/80 bg-slate-800/80 px-2.5 py-1.5 font-mono text-[11px] text-slate-300"
            >
                <span class="font-semibold text-cyan-300">
                    {{ reportTypeLabel(reportType) }}
                </span>
                <span class="text-slate-500">|</span>
                <span>
                    P:<span class="text-amber-300">{{ statsByType[reportType]?.pending ?? 0 }}</span>
                </span>
                <span>
                    R:<span class="text-sky-300">{{ statsByType[reportType]?.processing ?? 0 }}</span>
                </span>
                <span>
                    OK:<span class="text-emerald-300">{{ statsByType[reportType]?.completed ?? 0 }}</span>
                </span>
                <span>
                    ERR:<span class="text-rose-300">{{ statsByType[reportType]?.failed ?? 0 }}</span>
                </span>
            </div>
        </div>
    </div>
</template>
