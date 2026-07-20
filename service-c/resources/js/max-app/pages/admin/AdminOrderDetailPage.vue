<script setup>
/**
 * Карточка заказа для проверяющего: клиент, адрес, оплата, состав, чат, approve/reject.
 * В разделе «Адреса» проверяющий подтверждает адрес и оплату независимо.
 */
import { computed, toRef } from 'vue';
import CompositionEditItemList from '../../components/admin/CompositionEditItemList.vue';
import CompositionMenuPickerSheet from '../../components/admin/CompositionMenuPickerSheet.vue';
import OrderChatPanel from '../../components/OrderChatPanel.vue';
import OrderSnapshotItemRow from '../../components/OrderSnapshotItemRow.vue';
import OrderReviewStageBadges from '../../components/OrderReviewStageBadges.vue';
import OrderStatusBadge from '../../components/OrderStatusBadge.vue';
import { useCompositionEdit } from '../../composables/useCompositionEdit';
import { useOrderDetailPaneLayout } from '../../composables/useOrderDetailPaneLayout';
import ConfirmCompositionSaveModal from './ConfirmCompositionSaveModal.vue';
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
    'composition-saved',
]);

const orderRef = computed(() => props.order);
const scopeRef = toRef(props, 'scope');

const {
    isEditMode,
    canEdit,
    draftGroups,
    draftItemsTotal,
    menu,
    menuLoading,
    menuError,
    saveLoading,
    saveError,
    showConfirmModal,
    menuPickerOpen,
    comboBuilderOpen,
    comboFirstDish,
    comboSecondDish,
    comboQuantity,
    comboTotal,
    canAddCombo,
    startEdit,
    cancelEdit,
    openMenuPicker,
    closeMenuPicker,
    openSaveConfirm,
    closeSaveConfirm,
    updateGroupQuantity,
    removeGroup,
    addDishFromMenu,
    startComboBuilder,
    closeComboBuilder,
    resetSecondComboDish,
    selectSecondComboDish,
    changeComboQuantity,
    handleAddCombo,
    confirmSave,
} = useCompositionEdit(orderRef, scopeRef);

const {
    activateDetails,
    activateChat,
    isChatActive,
    isDetailsActive,
    detailsPaneClass,
    chatPaneClass,
} = useOrderDetailPaneLayout();

const isAddressScope = computed(() => props.scope === 'address');
const isCompositionScope = computed(() => props.scope === 'composition');

const isAddressPending = computed(() => props.order.address_review_status === 'pending');
const isPaymentPending = computed(() => props.order.payment_review_status === 'pending');

const displayItemsTotal = computed(() => {
    if (isEditMode.value) {
        return draftItemsTotal.value;
    }

    return props.order.items_total;
});

const compositionActionError = computed(() => saveError.value || props.actionError);

const hasBottomActions = computed(() => {
    if (props.loading) {
        return false;
    }

    if (isCompositionScope.value) {
        return true;
    }

    return isAddressScope.value && (isAddressPending.value || isPaymentPending.value);
});

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

function handleConfirmSave() {
    confirmSave((updatedOrder) => {
        emit('composition-saved', updatedOrder);
    });
}
</script>

<template>
    <div class="flex h-full min-h-0 flex-col overflow-hidden">
        <header class="sticky top-0 z-10 shrink-0 border-b border-gray-200 bg-white px-3 py-2">
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-gray-600 transition hover:bg-gray-100"
                    aria-label="Назад"
                    @click="emit('back')"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <div class="min-w-0 flex-1">
                    <div class="flex min-w-0 items-center gap-1.5">
                        <h1 class="truncate text-base font-semibold text-gray-900">Заказ №{{ order.id }}</h1>
                        <OrderStatusBadge :order="order" size="sm" />
                        <span class="hidden min-w-0 truncate text-xs text-max-muted sm:inline">{{ order.restaurant_name }}</span>
                    </div>
                    <div class="mt-0.5 flex min-w-0 items-center gap-1.5">
                        <p class="truncate text-xs text-max-muted sm:hidden">{{ order.restaurant_name }}</p>
                        <OrderReviewStageBadges :order="order" />
                    </div>
                </div>
            </div>
        </header>

        <main class="flex min-h-0 flex-1 flex-col gap-2 overflow-hidden px-3 py-2">
            <div v-if="loading" class="flex items-center justify-center py-16">
                <div class="h-8 w-8 animate-spin rounded-full border-4 border-max-primary border-t-transparent" />
            </div>

            <template v-else>
                <div
                    class="space-y-2 overflow-y-auto rounded-2xl"
                    :class="[
                        detailsPaneClass,
                        isDetailsActive ? 'ring-1 ring-max-primary/15' : '',
                    ]"
                    @pointerdown="activateDetails"
                >
                <div class="rounded-2xl border border-gray-100 bg-white p-3 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-max-muted">Клиент</p>
                    <p class="mt-1 text-sm text-gray-900">{{ formatCustomerName(order.customer) }}</p>
                </div>

                <div
                    class="rounded-2xl border bg-white p-3 shadow-sm"
                    :class="isAddressScope && isAddressPending ? 'border-max-primary/40 ring-1 ring-max-primary/10' : 'border-gray-100'"
                >
                    <p class="text-xs font-medium uppercase tracking-wide text-max-muted">Адрес доставки</p>
                    <p class="mt-1 text-sm text-gray-900">{{ order.delivery_address || '—' }}</p>
                </div>

                <div
                    v-if="isAddressScope"
                    class="rounded-2xl border bg-white p-3 shadow-sm"
                    :class="isPaymentPending ? 'border-max-primary/40 ring-1 ring-max-primary/10' : 'border-gray-100'"
                >
                    <p class="text-xs font-medium uppercase tracking-wide text-max-muted">Оплата</p>
                    <p class="mt-1 text-xl font-bold text-gray-900">{{ order.total }} ₽</p>
                    <p class="mt-1 text-sm text-max-muted">
                        Подтвердите, что оплата от клиента получена
                    </p>
                </div>

                <div
                    class="rounded-2xl border bg-white p-3 shadow-sm"
                    :class="isCompositionScope ? 'border-max-primary/40 ring-1 ring-max-primary/10' : 'border-gray-100'"
                >
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-medium uppercase tracking-wide text-max-muted">Состав заказа</p>
                        <button
                            v-if="isCompositionScope && canEdit && !isEditMode"
                            type="button"
                            class="shrink-0 text-xs font-medium text-max-primary transition hover:text-max-primary-hover"
                            @click="startEdit"
                        >
                            Редактировать
                        </button>
                    </div>

                    <template v-if="isEditMode">
                        <CompositionEditItemList
                            class="mt-3"
                            :groups="draftGroups"
                            @update-quantity="updateGroupQuantity"
                            @remove-group="removeGroup"
                        />

                        <div class="mt-3">
                            <button
                                type="button"
                                class="w-full rounded-xl border border-max-primary/30 bg-max-primary/5 px-3 py-2 text-xs font-medium text-max-primary transition hover:bg-max-primary/10"
                                @click="openMenuPicker"
                            >
                                Добавить блюдо
                            </button>
                        </div>

                        <p
                            v-if="menuError"
                            class="mt-2 text-xs text-red-600"
                        >
                            {{ menuError }}
                        </p>
                    </template>

                    <ul
                        v-else
                        class="mt-3 space-y-2"
                    >
                        <OrderSnapshotItemRow
                            v-for="(item, index) in order.items_snapshot"
                            :key="index"
                            :item="item"
                            :items-snapshot="order.items_snapshot"
                        />
                    </ul>

                    <div class="mt-4 border-t border-gray-100 pt-3 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-max-muted">Сумма блюд</span>
                            <span class="font-medium text-gray-900">{{ displayItemsTotal }} ₽</span>
                        </div>
                        <div
                            v-if="!isEditMode && order.delivery_cost !== null"
                            class="mt-1.5 flex items-center justify-between"
                        >
                            <span class="text-max-muted">Доставка</span>
                            <span class="font-medium text-gray-900">{{ order.delivery_cost }} ₽</span>
                        </div>
                        <div
                            v-if="!isEditMode"
                            class="mt-2 flex items-center justify-between border-t border-gray-100 pt-2"
                        >
                            <span class="font-medium text-gray-900">Итого</span>
                            <span class="text-lg font-bold text-gray-900">{{ order.total }} ₽</span>
                        </div>
                        <p
                            v-else
                            class="mt-2 text-xs text-max-muted"
                        >
                            Доставка и итог пересчитаются после сохранения
                        </p>
                    </div>
                </div>

                <div
                    v-if="compositionActionError && !showConfirmModal"
                    class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                >
                    {{ compositionActionError }}
                </div>
                <div
                    v-else-if="actionError && !isCompositionScope"
                    class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                >
                    {{ actionError }}
                </div>
                </div>

                <OrderChatPanel
                    :order-id="order.id"
                    perspective="admin"
                    compact
                    :active="isChatActive"
                    :safe-area-bottom="!hasBottomActions"
                    :class="chatPaneClass"
                    @activate="activateChat"
                    @messages-read="emit('messages-read')"
                />
            </template>
        </main>

        <footer
            v-if="!loading && isCompositionScope && isEditMode"
            class="shrink-0 border-t border-gray-200 bg-white px-3 py-2 safe-area-bottom"
        >
            <div class="flex gap-2">
                <button
                    type="button"
                    class="flex-1 rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-50"
                    :disabled="saveLoading"
                    @click="cancelEdit"
                >
                    Отмена
                </button>
                <button
                    type="button"
                    class="flex-1 rounded-xl bg-max-primary px-3 py-2 text-xs font-medium text-white transition hover:bg-max-primary-hover disabled:opacity-50"
                    :disabled="saveLoading || draftGroups.length === 0"
                    @click="openSaveConfirm"
                >
                    Сохранить
                </button>
            </div>
        </footer>

        <footer
            v-else-if="!loading && isCompositionScope"
            class="shrink-0 border-t border-gray-200 bg-white px-3 py-2 safe-area-bottom"
        >
            <div class="flex gap-2">
                <button
                    type="button"
                    class="flex-1 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-700 transition hover:bg-red-100 disabled:opacity-50"
                    :disabled="loading || actionLoading"
                    @click="emit('open-reject', 'composition')"
                >
                    Отклонить
                </button>
                <button
                    type="button"
                    class="flex-1 rounded-xl bg-max-primary px-3 py-2 text-xs font-medium text-white transition hover:bg-max-primary-hover disabled:opacity-50"
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
            class="shrink-0 space-y-1.5 border-t border-gray-200 bg-white px-3 py-2 safe-area-bottom"
        >
            <div v-if="isAddressPending" class="flex items-center gap-2">
                <span class="w-12 shrink-0 text-[10px] font-medium uppercase leading-tight tracking-wide text-max-muted">Адрес</span>
                <div class="flex min-w-0 flex-1 gap-2">
                    <button
                        type="button"
                        class="flex-1 rounded-xl border border-red-200 bg-red-50 px-2 py-2 text-xs font-medium text-red-700 transition hover:bg-red-100 disabled:opacity-50"
                        :disabled="actionLoading"
                        @click="emit('open-reject', 'address')"
                    >
                        Отклонить
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-xl bg-max-primary px-2 py-2 text-xs font-medium text-white transition hover:bg-max-primary-hover disabled:opacity-50"
                        :disabled="actionLoading"
                        @click="emit('approve-address')"
                    >
                        <span v-if="actionLoading">…</span>
                        <span v-else>Подтвердить</span>
                    </button>
                </div>
            </div>

            <div v-if="isPaymentPending" class="flex items-center gap-2">
                <span class="w-12 shrink-0 text-[10px] font-medium uppercase leading-tight tracking-wide text-max-muted">Оплата</span>
                <div class="flex min-w-0 flex-1 gap-2">
                    <button
                        type="button"
                        class="flex-1 rounded-xl border border-red-200 bg-red-50 px-2 py-2 text-xs font-medium text-red-700 transition hover:bg-red-100 disabled:opacity-50"
                        :disabled="actionLoading"
                        @click="emit('open-reject', 'payment')"
                    >
                        Не получена
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-xl bg-max-primary px-2 py-2 text-xs font-medium text-white transition hover:bg-max-primary-hover disabled:opacity-50"
                        :disabled="actionLoading"
                        @click="emit('approve-payment')"
                    >
                        <span v-if="actionLoading">…</span>
                        <span v-else>Получена</span>
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

        <ConfirmCompositionSaveModal
            :open="showConfirmModal"
            :groups="draftGroups"
            :items-total="draftItemsTotal"
            :loading="saveLoading"
            :error="saveError"
            @close="closeSaveConfirm"
            @confirm="handleConfirmSave"
        />

        <CompositionMenuPickerSheet
            :open="menuPickerOpen"
            :menu="menu"
            :loading="menuLoading"
            :error="menuError"
            :combo-builder-open="comboBuilderOpen"
            :combo-first-dish="comboFirstDish"
            :combo-second-dish="comboSecondDish"
            :combo-quantity="comboQuantity"
            :combo-total="comboTotal"
            :can-add-combo="canAddCombo"
            @close="closeMenuPicker"
            @add-dish="addDishFromMenu"
            @start-combo="startComboBuilder"
            @reset-second-combo="resetSecondComboDish"
            @change-combo-quantity="changeComboQuantity"
            @add-combo="handleAddCombo"
            @select-second-combo-dish="selectSecondComboDish"
            @close-combo-builder="closeComboBuilder"
        />
    </div>
</template>
