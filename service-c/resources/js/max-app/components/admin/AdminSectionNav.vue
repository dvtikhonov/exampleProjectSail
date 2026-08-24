<script setup>
/**
 * Переключатель разделов админки: заказы / ручные заказы / меню.
 */
import { computed, watch } from 'vue';
import { useAiAccess } from '../../composables/useAiAccess';
import { ADMIN_SECTIONS } from '../../constants/views';

const props = defineProps({
    adminSection: {
        type: String,
        required: true,
    },
    hasOrderReviewRoles: {
        type: Boolean,
        default: false,
    },
    hasMaxManagerRole: {
        type: Boolean,
        default: false,
    },
    hasMenuManagerRole: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['change']);

const {
    enabled,
    loading,
    toggleLoading,
    error,
    loadStatus,
    toggleAccess,
    startPolling,
    stopPolling,
} = useAiAccess();

const isAiAccessBusy = computed(() => loading.value || toggleLoading.value);
const aiAccessBadgeClass = computed(() => (
    enabled.value
        ? 'bg-emerald-100 text-emerald-700 ring-emerald-200'
        : 'bg-rose-100 text-rose-700 ring-rose-200'
));
const aiAccessBadgeText = computed(() => (enabled.value ? 'вкл' : 'откл'));

async function onToggleAiAccess() {
    await toggleAccess();
}

watch(
    () => props.hasMaxManagerRole,
    async (hasRole) => {
        if (hasRole) {
            await loadStatus();
            startPolling();
            return;
        }

        stopPolling();
    },
    { immediate: true }
);
</script>

<template>
    <header class="z-20 shrink-0 border-b border-gray-200 bg-white safe-area-top">
        <div class="flex flex-col gap-2 px-2 py-2 sm:flex-row sm:items-end sm:justify-between sm:px-0 sm:py-0">
            <nav class="flex flex-1" aria-label="Разделы админки">
                <button
                    v-if="hasOrderReviewRoles"
                    type="button"
                    class="flex-1 border-b-2 px-4 py-2 text-sm font-medium transition"
                    :class="
                        adminSection === ADMIN_SECTIONS.orders
                            ? 'border-max-primary text-max-primary'
                            : 'border-transparent text-max-muted hover:text-gray-700'
                    "
                    @click="$emit('change', ADMIN_SECTIONS.orders)"
                >
                    Заказы
                </button>
                <button
                    v-if="hasMaxManagerRole"
                    type="button"
                    class="flex-1 border-b-2 px-4 py-2 text-sm font-medium transition"
                    :class="
                        adminSection === ADMIN_SECTIONS.manualOrders
                            ? 'border-max-primary text-max-primary'
                            : 'border-transparent text-max-muted hover:text-gray-700'
                    "
                    @click="$emit('change', ADMIN_SECTIONS.manualOrders)"
                >
                    Ручные заказы
                </button>
                <button
                    v-if="hasMenuManagerRole"
                    type="button"
                    class="flex-1 border-b-2 px-4 py-2 text-sm font-medium transition"
                    :class="
                        adminSection === ADMIN_SECTIONS.menu
                            ? 'border-max-primary text-max-primary'
                            : 'border-transparent text-max-muted hover:text-gray-700'
                    "
                    @click="$emit('change', ADMIN_SECTIONS.menu)"
                >
                    Меню
                </button>
            </nav>

            <div v-if="hasMaxManagerRole" class="flex flex-col items-start gap-1 pb-1 pr-2 sm:items-end sm:pr-4">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-800 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="isAiAccessBusy"
                    @click="onToggleAiAccess"
                >
                    <span>Вкл. доступ AI</span>
                    <span
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold uppercase ring-1 ring-inset"
                        :class="aiAccessBadgeClass"
                    >
                        {{ aiAccessBadgeText }}
                    </span>
                </button>
                <p v-if="error" class="text-xs text-rose-600">
                    {{ error }}
                </p>
            </div>
        </div>
    </header>
</template>
