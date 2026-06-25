<script setup>
/**
 * Карточка заказа для проверяющего: клиент, адрес, оплата, состав, чат, approve/reject.
 * В разделе «Адреса» проверяющий подтверждает адрес и оплату независимо.
 */
import { computed } from 'vue';
import DishImage from '../../components/DishImage.vue';
import OrderChatPanel from '../../components/OrderChatPanel.vue';
import OrderReviewStageBadges from '../../components/OrderReviewStageBadges.vue';
import OrderStatusBadge from '../../components/OrderStatusBadge.vue';
import RejectOrderModal from './RejectOrderModal.vue';

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
    scope: {
        type: String,
        required: true,
        validator: (value) => ['address', 'composition'].includes(value),
    },
    loading: {
        type: Boolean,
        default: false,
    },
    actionLoading: {
        type: Boolean,
        default: false,
    },
    actionError: {
        type: String,
        default: '',
    },
    showRejectModal: {
        type: Boolean,
        default: false,
    },
    rejectTarget: {
        type: String,
        default: 'address',
        validator: (value) => ['address', 'payment', 'composition'].includes(value),
    },
});

const emit = defineEmits([
    'back',
    'approve-address',
    'approve-payment',
    'approve-composition',
    'open-reject',
    'close-reject',
    'reject',
    'messages-read',
]);

const isAddressScope = computed(() => props.scope === 'address');
const isCompositionScope = computed(() => props.scope === 'composition');

const isAddressPending = computed(() => props.order.address_review_status === 'pending');
const isPaymentPending = computed(() => props.order.payment_review_status === 'pending');

const rejectModalTitle = computed(() => {
    if (props.rejectTarget === 'payment') {
        return 'Отклонить оплату';
    }

    if (props.rejectTarget === 'composition') {
        return 'Отклонить состав заказа';
    }

    return 'Отклонить адрес доставки';
});

/**
 * @param {{ first_name?: string|null, last_name?: string|null, username?: string|null, max_user_id: number }} customer
 */
function formatCustomerName(customer) {
    const parts = [customer.first_name, customer.last_name].filter(Boolean);

    if (parts.length > 0) {
        return parts.join(' ');
    }

    if (customer.username) {
        return `@${customer.username}`;
    }

    return `ID ${customer.max_user_id}`;
}
</script>

<template>
    <div class="flex min-h-dvh flex-col">
        <header class="sticky top-0 z-10 border-b border-gray-200 bg-white px-4 py-3 safe-area-top">
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class="flex h-9 w-9 items-center justify-center rounded-full text-gray-600 transition hover:bg-gray-100"
                    aria-label="Назад"
                    @click="emit('back')"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="truncate text-lg font-semibold text-gray-900">Заказ №{{ order.id }}</h1>
                        <OrderStatusBadge :order="order" size="md" />
                    </div>
                    <p class="truncate text-sm text-max-muted">{{ order.restaurant_name }}</p>
                    <OrderReviewStageBadges class="mt-1.5" :order="order" />
                </div>
            </div>
        </header>

        <main class="flex min-h-0 flex-1 flex-col gap-4 overflow-hidden px-4 py-4">
            <div v-if="loading" class="flex items-center justify-center py-16">
                <div class="h-8 w-8 animate-spin rounded-full border-4 border-max-primary border-t-transparent" />
            </div>

            <template v-else>
                <div class="shrink-0 space-y-4 overflow-y-auto">
                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-max-muted">Клиент</p>
                    <p class="mt-1 text-sm text-gray-900">{{ formatCustomerName(order.customer) }}</p>
                </div>

                <div
                    class="rounded-2xl border bg-white p-4 shadow-sm"
                    :class="isAddressScope && isAddressPending ? 'border-max-primary/40 ring-1 ring-max-primary/10' : 'border-gray-100'"
                >
                    <p class="text-xs font-medium uppercase tracking-wide text-max-muted">Адрес доставки</p>
                    <p class="mt-1 text-sm text-gray-900">{{ order.delivery_address || '—' }}</p>
                </div>

                <div
                    v-if="isAddressScope"
                    class="rounded-2xl border bg-white p-4 shadow-sm"
                    :class="isPaymentPending ? 'border-max-primary/40 ring-1 ring-max-primary/10' : 'border-gray-100'"
                >
                    <p class="text-xs font-medium uppercase tracking-wide text-max-muted">Оплата</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ order.total }} ₽</p>
                    <p class="mt-1 text-sm text-max-muted">
                        Подтвердите, что оплата от клиента получена
                    </p>
                </div>

                <div
                    class="rounded-2xl border bg-white p-4 shadow-sm"
                    :class="isCompositionScope ? 'border-max-primary/40 ring-1 ring-max-primary/10' : 'border-gray-100'"
                >
                    <p class="text-xs font-medium uppercase tracking-wide text-max-muted">Состав заказа</p>
                    <ul class="mt-3 space-y-2">
                        <li
                            v-for="(item, index) in order.items_snapshot"
                            :key="index"
                            class="flex items-center gap-3 text-sm"
                        >
                            <DishImage :image-url="item.image_url" :alt="item.dish_name" size="sm" />
                            <span class="min-w-0 flex-1 text-gray-700">{{ item.dish_name }} × {{ item.quantity }}</span>
                            <span class="shrink-0 font-medium text-gray-900">{{ item.line_total }} ₽</span>
                        </li>
                    </ul>

                    <div class="mt-4 border-t border-gray-100 pt-3 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-max-muted">Сумма блюд</span>
                            <span class="font-medium text-gray-900">{{ order.items_total }} ₽</span>
                        </div>
                        <div
                            v-if="order.delivery_cost !== null"
                            class="mt-1.5 flex items-center justify-between"
                        >
                            <span class="text-max-muted">Доставка</span>
                            <span class="font-medium text-gray-900">{{ order.delivery_cost }} ₽</span>
                        </div>
                        <div class="mt-2 flex items-center justify-between border-t border-gray-100 pt-2">
                            <span class="font-medium text-gray-900">Итого</span>
                            <span class="text-lg font-bold text-gray-900">{{ order.total }} ₽</span>
                        </div>
                    </div>
                </div>

                <div
                    v-if="actionError"
                    class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                >
                    {{ actionError }}
                </div>
                </div>

                <OrderChatPanel
                    :order-id="order.id"
                    perspective="admin"
                    class="min-h-[220px] flex-1"
                    @messages-read="emit('messages-read')"
                />
            </template>
        </main>

        <footer
            v-if="!loading && isCompositionScope"
            class="sticky bottom-0 border-t border-gray-200 bg-white px-4 py-4 safe-area-bottom"
        >
            <div class="flex gap-3">
                <button
                    type="button"
                    class="flex-1 rounded-2xl border border-red-200 bg-red-50 px-4 py-3.5 text-sm font-medium text-red-700 transition hover:bg-red-100 disabled:opacity-50"
                    :disabled="loading || actionLoading"
                    @click="emit('open-reject', 'composition')"
                >
                    Отклонить
                </button>
                <button
                    type="button"
                    class="flex-1 rounded-2xl bg-max-primary px-4 py-3.5 text-sm font-medium text-white transition hover:bg-max-primary-hover disabled:opacity-50"
                    :disabled="loading || actionLoading"
                    @click="emit('approve-composition')"
                >
                    <span v-if="actionLoading">Обработка…</span>
                    <span v-else>Подтвердить</span>
                </button>
            </div>
        </footer>

        <footer
            v-else-if="!loading && isAddressScope && (isAddressPending || isPaymentPending)"
            class="sticky bottom-0 space-y-3 border-t border-gray-200 bg-white px-4 py-4 safe-area-bottom"
        >
            <div v-if="isAddressPending" class="space-y-2">
                <p class="text-xs font-medium uppercase tracking-wide text-max-muted">Адрес</p>
                <div class="flex gap-3">
                    <button
                        type="button"
                        class="flex-1 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700 transition hover:bg-red-100 disabled:opacity-50"
                        :disabled="actionLoading"
                        @click="emit('open-reject', 'address')"
                    >
                        Отклонить
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-2xl bg-max-primary px-4 py-3 text-sm font-medium text-white transition hover:bg-max-primary-hover disabled:opacity-50"
                        :disabled="actionLoading"
                        @click="emit('approve-address')"
                    >
                        <span v-if="actionLoading">Обработка…</span>
                        <span v-else>Подтвердить адрес</span>
                    </button>
                </div>
            </div>

            <div v-if="isPaymentPending" class="space-y-2">
                <p class="text-xs font-medium uppercase tracking-wide text-max-muted">Оплата</p>
                <div class="flex gap-3">
                    <button
                        type="button"
                        class="flex-1 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700 transition hover:bg-red-100 disabled:opacity-50"
                        :disabled="actionLoading"
                        @click="emit('open-reject', 'payment')"
                    >
                        Не получена
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-2xl bg-max-primary px-4 py-3 text-sm font-medium text-white transition hover:bg-max-primary-hover disabled:opacity-50"
                        :disabled="actionLoading"
                        @click="emit('approve-payment')"
                    >
                        <span v-if="actionLoading">Обработка…</span>
                        <span v-else>Оплата получена</span>
                    </button>
                </div>
            </div>
        </footer>

        <RejectOrderModal
            :open="showRejectModal"
            :loading="actionLoading"
            :error="actionError"
            :title="rejectModalTitle"
            @close="emit('close-reject')"
            @submit="(comment) => emit('reject', comment)"
        />
    </div>
</template>
