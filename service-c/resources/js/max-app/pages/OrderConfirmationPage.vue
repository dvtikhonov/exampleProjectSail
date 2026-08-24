<script setup>
/**
 * Экран после успешной отправки заявки.
 * Клиентский заказ — ожидание проверки; ручной — сразу принят к исполнению.
 *
 * @typedef {import('../api/types.js').OrderDto} OrderDto
 */
import EmptyStateIcon from '../components/EmptyStateIcon.vue';
import OrderSnapshotItemRow from '../components/OrderSnapshotItemRow.vue';
import { formatIsoDateRu } from '../utils/formatIsoDateRu';
import { computed } from 'vue';

const props = defineProps({
    /** Оформленный заказ (OrderDto после submit) */
    order: {
        type: Object,
        required: true,
    },
    isSingleRestaurantMode: {
        type: Boolean,
        default: false,
    },
    /** Ручной заказ менеджера: другой текст и CTA «Назад к списку» */
    manualOrderMode: {
        type: Boolean,
        default: false,
    },
    customerLabel: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['back-to-restaurants', 'go-to-order', 'back-to-users']);

const deliveryDateLabel = computed(() => formatIsoDateRu(props.order?.delivery_date) || '—');
</script>

<template>
    <!--
        В админ-секции родительский контейнер использует `overflow-hidden`,
        поэтому `min-h-dvh` может "вылезать" за доступную высоту и обрезать CTA-кнопку.
        Делаем контейнер гибким и включаем прокрутку при переполнении.
    -->
    <div class="flex min-h-0 flex-1 flex-col items-center justify-center overflow-y-auto px-6 py-12 text-center">
        <div
            class="mb-6 flex h-20 w-20 items-center justify-center rounded-full"
            :class="manualOrderMode ? 'bg-green-100' : 'bg-amber-100'"
        >
            <EmptyStateIcon
                :name="manualOrderMode ? 'check' : 'clock'"
                size="xl"
            />
        </div>
        <h1 class="text-2xl font-bold text-gray-900">
            {{ manualOrderMode ? 'Заявка оформлена' : 'Заявка отправлена на проверку' }}
        </h1>
        <p class="mt-2 text-sm text-max-muted">
            <template v-if="manualOrderMode">
                Заказ №{{ order.id }}
                <template v-if="customerLabel"> для потребителя {{ customerLabel }}</template>
                принят к исполнению. Адрес, оплата и состав подтверждены.
            </template>
            <template v-else>
                Заказ №{{ order.id }} ожидает подтверждения. Мы сообщим вам в MAX, когда заявка будет обработана.
            </template>
        </p>

        <div class="mt-8 w-full max-w-sm rounded-2xl border border-gray-100 bg-white p-4 text-left shadow-sm">
            <div class="space-y-3 border-b border-gray-100 pb-3">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-max-muted">Адрес доставки</p>
                    <p class="mt-1 text-sm text-gray-900">{{ order.delivery_address }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-max-muted">Дата доставки</p>
                    <p class="mt-1 text-sm text-gray-900">{{ deliveryDateLabel }}</p>
                </div>
            </div>

            <ul class="mt-3 space-y-2">
                <OrderSnapshotItemRow
                    v-for="(item, index) in order.items_snapshot"
                    :key="index"
                    :item="item"
                    :items-snapshot="order.items_snapshot"
                />
            </ul>

            <div class="mt-4 border-t border-gray-100 pt-3 text-sm">
                <template v-if="order.delivery_applicable">
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="text-max-muted">Сумма блюд</span>
                            <span class="font-medium text-gray-900">{{ order.items_total }} ₽</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-max-muted">Доставка</span>
                            <span class="font-medium text-gray-900">{{ order.delivery_cost }} ₽</span>
                        </div>
                        <div class="flex items-center justify-between border-t border-gray-100 pt-2">
                            <span class="font-medium text-gray-900">Итого</span>
                            <span class="text-lg font-bold text-gray-900">{{ order.total }} ₽</span>
                        </div>
                    </div>
                </template>
                <div v-else class="flex items-center justify-between">
                    <span class="font-medium text-gray-900">Итого</span>
                    <span class="text-lg font-bold text-gray-900">{{ order.total }} ₽</span>
                </div>
            </div>
        </div>

        <button
            v-if="manualOrderMode"
            type="button"
            class="mt-8 w-full max-w-sm rounded-2xl bg-max-primary px-6 py-3.5 font-medium text-white transition hover:bg-max-primary-hover"
            @click="emit('back-to-users')"
        >
            Назад к списку
        </button>

        <template v-else>
            <button
                type="button"
                class="mt-8 w-full max-w-sm rounded-2xl bg-max-primary px-6 py-3.5 font-medium text-white transition hover:bg-max-primary-hover"
                @click="emit('go-to-order')"
            >
                Перейти к заказу
            </button>

            <button
                type="button"
                class="mt-3 w-full max-w-sm rounded-2xl border border-gray-200 bg-white px-6 py-3.5 font-medium text-gray-700 transition hover:bg-gray-50"
                @click="emit('back-to-restaurants')"
            >
                {{ isSingleRestaurantMode ? 'К меню' : 'К ресторанам' }}
            </button>
        </template>
    </div>
</template>
