<script setup>
/**
 * Баннер статуса синхронизации с Яндекс.Картами.
 *
 * Цвет и спиннер зависят от status (pending/syncing/completed/failed).
 */
import { computed } from 'vue';
import { formatDate } from '../../utils/formatters';
import { isSyncInProgress as checkSyncInProgress, syncStatusLabel } from '../../utils/syncStatus';

const props = defineProps({
    status: {
        type: String,
        default: null,
    },
    lastSyncedAt: {
        type: String,
        default: null,
    },
    syncError: {
        type: String,
        default: null,
    },
});

const inProgress = computed(() => checkSyncInProgress(props.status));

const bannerClass = computed(() => {
    if (props.status === 'failed') {
        return 'border-red-200 bg-red-50 text-red-800';
    }

    if (inProgress.value) {
        return 'border-amber-200 bg-amber-50 text-amber-900';
    }

    return 'border-slate-200 bg-slate-50 text-slate-700';
});
</script>

<template>
    <div>
        <div
            class="mt-6 flex flex-wrap items-center gap-3 rounded-lg border px-4 py-3 text-sm"
            :class="bannerClass"
        >
            <span
                v-if="inProgress"
                class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-amber-400/40 border-t-amber-700"
                aria-hidden="true"
            />
            <span>
                Статус синхронизации:
                <span class="font-medium">{{ syncStatusLabel(status) }}</span>
            </span>
            <span
                v-if="lastSyncedAt && status === 'completed'"
                class="text-slate-500"
            >
                · обновлено {{ formatDate(lastSyncedAt) }}
            </span>
        </div>

        <p
            v-if="syncError && status === 'failed'"
            class="mt-2 text-sm text-red-700"
        >
            {{ syncError }}
        </p>
    </div>
</template>
