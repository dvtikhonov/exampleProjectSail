<script setup>
/**
 * Форма выгрузки отчётов Food: период, ресторан, тип → .xlsx в чат MAX.
 */
import { onMounted, toRef } from 'vue';
import AppSelect from '../AppSelect.vue';
import { useFoodReport } from '../../composables/useFoodReport';

const props = defineProps({
    /**
     * Ресторан из flow оформления (если уже выбран) — подставляется и select блокируется.
     */
    preselectedRestaurantId: {
        type: [Number, String],
        default: null,
    },
});

const preselectedRestaurantId = toRef(props, 'preselectedRestaurantId');

const {
    dateFrom,
    dateTo,
    restaurantId,
    reportType,
    restaurantsLoading,
    restaurantsError,
    restaurantLocked,
    restaurantSelectOptions,
    reportTypeOptions,
    sending,
    sendError,
    sendSuccess,
    canSend,
    loadRestaurants,
    setDateFrom,
    setDateTo,
    setRestaurantId,
    setReportType,
    send,
} = useFoodReport({ preselectedRestaurantId });

onMounted(() => {
    loadRestaurants();
});

/**
 * @param {Event} event
 */
function onDateFromChange(event) {
    setDateFrom(/** @type {HTMLInputElement} */ (event.target).value);
}

/**
 * @param {Event} event
 */
function onDateToChange(event) {
    setDateTo(/** @type {HTMLInputElement} */ (event.target).value);
}
</script>

<template>
    <section
        class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm"
        aria-labelledby="food-report-heading"
    >
        <div class="mb-3">
            <h2
                id="food-report-heading"
                class="text-sm font-semibold text-gray-900"
            >
                Отчёты
            </h2>
            <p class="mt-0.5 text-xs text-max-muted">
                Только выполненные заказы. Файл .xlsx придёт в чат MAX.
            </p>
        </div>

        <div
            v-if="restaurantsError"
            class="mb-3 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
        >
            {{ restaurantsError }}
            <button
                type="button"
                class="mt-1 block text-sm font-medium text-red-800 underline"
                @click="loadRestaurants"
            >
                Повторить
            </button>
        </div>

        <div
            v-if="sendError"
            class="mb-3 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
        >
            {{ sendError }}
        </div>

        <div
            v-if="sendSuccess"
            class="mb-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800"
        >
            {{ sendSuccess }}
        </div>

        <div class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-900"
                        for="food-report-date-from"
                    >
                        С даты
                    </label>
                    <input
                        id="food-report-date-from"
                        type="date"
                        class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-max-primary focus:ring-2 focus:ring-max-primary/20"
                        :value="dateFrom"
                        :disabled="sending"
                        @change="onDateFromChange"
                    >
                </div>
                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-900"
                        for="food-report-date-to"
                    >
                        По дату
                    </label>
                    <input
                        id="food-report-date-to"
                        type="date"
                        class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-max-primary focus:ring-2 focus:ring-max-primary/20"
                        :value="dateTo"
                        :disabled="sending"
                        @change="onDateToChange"
                    >
                </div>
            </div>

            <div>
                <label
                    class="mb-1.5 block text-sm font-medium text-gray-900"
                    for="food-report-restaurant"
                >
                    Ресторан
                </label>
                <AppSelect
                    id="food-report-restaurant"
                    :model-value="restaurantId"
                    :options="restaurantSelectOptions"
                    :disabled="restaurantLocked || restaurantsLoading || sending"
                    placeholder="Выберите ресторан"
                    @update:model-value="setRestaurantId"
                />
                <p
                    v-if="restaurantLocked"
                    class="mt-1 text-xs text-max-muted"
                >
                    Ресторан подставлен из текущего оформления
                </p>
            </div>

            <div>
                <label
                    class="mb-1.5 block text-sm font-medium text-gray-900"
                    for="food-report-type"
                >
                    Тип отчёта
                </label>
                <AppSelect
                    id="food-report-type"
                    :model-value="reportType"
                    :options="reportTypeOptions"
                    :disabled="sending"
                    placeholder="Выберите отчёт"
                    @update:model-value="setReportType"
                />
            </div>

            <button
                type="button"
                class="flex w-full items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium transition"
                :disabled="!canSend || sending"
                :class="canSend && !sending
                    ? 'bg-max-primary text-white active:scale-[0.99]'
                    : sending
                        ? 'bg-max-primary text-white opacity-90'
                        : 'cursor-not-allowed bg-gray-100 text-gray-400'"
                @click="send"
            >
                <span
                    v-if="sending"
                    class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"
                    aria-hidden="true"
                />
                {{ sending ? 'Отправка…' : 'Отправить в MAX' }}
            </button>
        </div>
    </section>
</template>
