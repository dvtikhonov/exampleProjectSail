import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

export const REPORT_JOB_TYPES = Object.freeze(['csv_download', 'html_email', 'max_message']);

const REPORT_TYPE_LABELS = Object.freeze({
    csv_download: 'CSV',
    html_email: 'Почта',
    max_message: 'MAX',
});

const emptyTypeStats = () => ({
    pending: 0,
    processing: 0,
    completed: 0,
    failed: 0,
    total: 0,
});

const normalizeTypeStats = (stats = {}) => ({
    pending: Number(stats.pending ?? 0),
    processing: Number(stats.processing ?? 0),
    completed: Number(stats.completed ?? 0),
    failed: Number(stats.failed ?? 0),
    total: Number(stats.total ?? 0),
});

const normalizeStatsPayload = (payload) => {
    const byTypeSource = payload?.by_type ?? payload?.byType ?? {};
    const byType = {};

    for (const reportType of REPORT_JOB_TYPES) {
        byType[reportType] = normalizeTypeStats(byTypeSource[reportType]);
    }

    return {
        byType,
        generatedAt: payload?.generated_at ?? payload?.generatedAt ?? null,
    };
};

export const reportTypeLabel = (reportType) => REPORT_TYPE_LABELS[reportType] ?? reportType;

export const useReportJobStats = (reportStatsUrl) => {
    const stats = ref(null);
    const isLoading = ref(false);
    const error = ref('');

    const applySnapshot = (payload) => {
        stats.value = normalizeStatsPayload(payload);
    };

    const fetchInitialStats = async () => {
        if (!reportStatsUrl) {
            return;
        }

        isLoading.value = true;
        error.value = '';

        try {
            const response = await fetch(reportStatsUrl, {
                headers: {
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Не удалось загрузить статистику отчётов.');
            }

            applySnapshot(await response.json());
        } catch (fetchError) {
            error.value = fetchError?.message ?? 'Не удалось загрузить статистику отчётов.';
        } finally {
            isLoading.value = false;
        }
    };

    const subscribeToStatsUpdates = () => {
        if (typeof window.Echo === 'undefined') {
            return;
        }

        window.Echo.private('report-jobs.stats')
            .listen('.ReportJobStatsChanged', (event) => {
                applySnapshot(event);
            });
    };

    const unsubscribeFromStatsUpdates = () => {
        if (typeof window.Echo === 'undefined') {
            return;
        }

        window.Echo.leave('report-jobs.stats');
    };

    const activeCount = (reportType) => {
        const typeStats = stats.value?.byType?.[reportType];

        if (!typeStats) {
            return 0;
        }

        return typeStats.pending + typeStats.processing;
    };

    const typeStats = (reportType) => stats.value?.byType?.[reportType] ?? emptyTypeStats();

    const statsByType = computed(() => stats.value?.byType ?? null);

    onMounted(async () => {
        await fetchInitialStats();
        subscribeToStatsUpdates();
    });

    onBeforeUnmount(() => {
        unsubscribeFromStatsUpdates();
    });

    return {
        stats,
        statsByType,
        isLoading,
        error,
        activeCount,
        typeStats,
        reportTypes: REPORT_JOB_TYPES,
        reportTypeLabel,
    };
};
