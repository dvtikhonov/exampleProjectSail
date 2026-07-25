<script setup>
/**
 * Просмотр ручного заказа в layout корзины: адрес, позиции, итоги (без редактирования).
 * Кнопка «Чат» активна, если у заказа есть сообщения.
 */
import { computed, ref } from 'vue';
import DishImage from '../../components/DishImage.vue';
import ManualOrderChatModal from '../../components/ManualOrderChatModal.vue';
import OrderStatusBadge from '../../components/OrderStatusBadge.vue';
import { formatDishWeight } from '../../utils/dishWeight';
import { buildSnapshotGroups, getSnapshotGroupTitle } from '../../utils/orderSnapshotGroups';
import { formatCustomerName } from '../../utils/formatCustomerName';

const props = defineProps({
    order: {
        type: Object,
        default: null,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
});

defineEmits(['back']);

const chatOpen = ref(false);

const orderGroups = computed(() => buildSnapshotGroups(props.order?.items_snapshot ?? []));

const deliveryApplicable = computed(() => props.order?.delivery_applicable === true);

const hasMessages = computed(() => props.order?.has_messages === true);

const customerLabel = computed(() => {
    if (!props.order?.customer) {
        return '';
    }

    return formatCustomerName(props.order.customer) || `ID ${props.order.customer.max_user_id}`;
});

/**
 * @param {object} group
 * @returns {string|null}
 */
function groupImageUrl(group) {
    if (group.type === 'combo') {
        return group.items[0]?.image_url ?? null;
    }

    return group.item?.image_url ?? null;
}

function openChat() {
    if (!hasMessages.value || props.order?.id == null) {
        return;
    }

    chatOpen.value = true;
}
</script>

<template>
    <div class="flex h-full min-h-0 flex-col overflow-hidden bg-white">
        <header class="shrink-0 rounded-b-2xl bg-max-surface px-4 pb-4 pt-3 safe-area-top">
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-gray-600 transition hover:bg-white/60"
                    aria-label="Назад"
                    @click="$emit('back')"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <div class="min-w-0 flex-1">
                    <div class="flex min-w-0 flex-wrap items-center gap-2">
                        <h1 class="truncate text-lg font-semibold text-gray-900">
                            Заказ №{{ order?.id ?? '…' }}
                        </h1>
                        <OrderStatusBadge v-if="order?.status" :order="order" size="sm" />
                    </div>
                    <p
                        v-if="order?.restaurant_name"
                        class="truncate text-sm text-max-muted"
                    >
                        {{ order.restaurant_name }}
                    </p>
                </div>
                <button
                    v-if="order && !loading && !error"
                    type="button"
                    class="flex h-9 shrink-0 items-center gap-1.5 rounded-xl px-3 text-sm font-medium transition"
                    :class="hasMessages
                        ? 'bg-max-primary text-white hover:bg-max-primary-hover'
                        : 'cursor-not-allowed bg-gray-100 text-gray-400'"
                    :disabled="!hasMessages"
                    :title="hasMessages ? 'Открыть чат' : 'Сообщений нет'"
                    :aria-label="hasMessages ? 'Открыть чат' : 'Чат недоступен — сообщений нет'"
                    @click="openChat"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
                        />
                    </svg>
                    Чат
                </button>
            </div>

            <div
                v-if="order && !loading"
                class="mt-3 rounded-xl border border-white/60 bg-white/70 px-3 py-2.5"
            >
                <p class="text-xs font-medium uppercase tracking-wide text-max-muted">Адрес доставки</p>
                <p class="mt-1 text-sm text-gray-900">{{ order.delivery_address || '—' }}</p>
                <p
                    v-if="customerLabel"
                    class="mt-1 truncate text-xs text-max-muted"
                >
                    {{ customerLabel }}
                </p>
            </div>
        </header>

        <main class="min-h-0 flex-1 overflow-y-auto px-4 py-4">
            <div v-if="loading" class="flex items-center justify-center py-16">
                <div class="h-8 w-8 animate-spin rounded-full border-4 border-max-primary border-t-transparent" />
            </div>

            <div
                v-else-if="error"
                class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
                {{ error }}
            </div>

            <div
                v-else-if="orderGroups.length === 0"
                class="py-16 text-center text-sm text-max-muted"
            >
                В заказе нет позиций
            </div>

            <ul
                v-else
                class="space-y-3"
                :class="deliveryApplicable ? 'pb-40' : 'pb-28'"
            >
                <li
                    v-for="group in orderGroups"
                    :key="group.key"
                    class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm"
                >
                    <div class="flex items-start gap-3">
                        <DishImage
                            :image-url="groupImageUrl(group)"
                            :alt="getSnapshotGroupTitle(group)"
                        />
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-900">
                                        {{ getSnapshotGroupTitle(group) }}
                                    </p>
                                    <p
                                        v-if="group.type !== 'combo' && formatDishWeight(group.item)"
                                        class="mt-0.5 text-sm text-max-muted"
                                    >
                                        {{ formatDishWeight(group.item) }}
                                    </p>
                                </div>
                            </div>

                            <ul
                                v-if="group.type === 'combo'"
                                class="mt-2 space-y-1 text-xs text-max-muted"
                            >
                                <li
                                    v-for="(component, index) in group.items"
                                    :key="`${group.key}-part-${index}`"
                                >
                                    <p class="truncate">{{ component.dish_name }}</p>
                                    <p
                                        v-if="formatDishWeight(component)"
                                        class="text-xs text-max-muted"
                                    >
                                        {{ formatDishWeight(component) }}
                                    </p>
                                </li>
                            </ul>

                            <div class="mt-3 flex items-end justify-between gap-3">
                                <p class="text-sm text-max-muted">
                                    × {{ group.quantity }}
                                </p>
                                <div class="flex shrink-0 flex-col items-end text-sm">
                                    <span
                                        v-if="group.type !== 'combo' && group.item?.unit_price != null"
                                        class="text-max-muted"
                                    >
                                        {{ group.item.unit_price }} ₽ × {{ group.quantity }}
                                    </span>
                                    <span class="font-semibold text-gray-900">{{ group.lineTotal }} ₽</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </li>
            </ul>
        </main>

        <div
            v-if="order && !loading && !error"
            class="max-app-shell-bottom shrink-0 border-t border-gray-200 bg-white px-4 py-3 safe-area-bottom"
        >
            <div class="space-y-1.5 text-sm">
                <template v-if="deliveryApplicable">
                    <p class="mb-1 text-base font-medium text-gray-900">Детали</p>
                    <div class="flex items-center justify-between">
                        <span class="text-max-muted">Сумма блюд</span>
                        <span class="font-medium text-gray-900">{{ order.items_total }} ₽</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-max-muted">Доставка</span>
                        <span class="font-medium text-gray-900">{{ order.delivery_cost }} ₽</span>
                    </div>
                    <div class="flex items-center justify-between border-t border-gray-100 pt-2 text-base">
                        <span class="font-medium text-gray-900">Итого</span>
                        <span class="text-xl font-bold text-gray-900">{{ order.total }} ₽</span>
                    </div>
                </template>
                <div
                    v-else
                    class="flex items-center justify-between text-base"
                >
                    <span class="font-medium text-gray-900">Итого</span>
                    <span class="text-xl font-bold text-gray-900">{{ order.total }} ₽</span>
                </div>
            </div>
        </div>

        <ManualOrderChatModal
            :open="chatOpen"
            :order-id="order?.id ?? null"
            @close="chatOpen = false"
        />
    </div>
</template>
