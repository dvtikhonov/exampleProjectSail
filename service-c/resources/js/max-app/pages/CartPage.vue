<script setup>
/**
 * Корзина: позиции, адрес доставки, подтверждение заявки.
 *
 * Адрес синхронизируется с сервером через debounce (родитель App.vue).
 * Модалка подтверждения перехватывает кнопку «Назад» через defineExpose.
 */
import { computed, ref, watch } from 'vue';
import DishImage from '../components/DishImage.vue';
import MyOrdersButton from '../components/MyOrdersButton.vue';
import { buildCartGroups, getCartGroupTitle } from '../utils/cartGroups';

const props = defineProps({
    cart: {
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
    submitting: {
        type: Boolean,
        default: false,
    },
    updatingItemId: {
        type: [Number, String],
        default: null,
    },
    savingAddress: {
        type: Boolean,
        default: false,
    },
    clearing: {
        type: Boolean,
        default: false,
    },
    ordersUnreadCount: {
        type: Number,
        default: 0,
    },
});

const emit = defineEmits([
    'update-quantity',
    'remove-item',
    'clear-cart',
    'submit-order',
    'go-back',
    'go-to-restaurants',
    'delivery-address-input',
    'delivery-address-blur',
    'open-orders',
]);

const localAddress = ref('');
const showOrderConfirm = ref(false);
const isAddressFocused = ref(false);
const quantityDrafts = ref({});
const focusedQuantityItemId = ref(null);

const MIN_QUANTITY = 1;
const MAX_QUANTITY = 99;

/** Не перезаписывать localAddress с сервера, пока пользователь редактирует поле */
watch(
    () => props.cart?.delivery_address,
    (value) => {
        if (isAddressFocused.value) {
            return;
        }

        localAddress.value = value ?? '';
    },
    { immediate: true },
);

const cartGroups = computed(() => buildCartGroups(props.cart));

const isEmpty = computed(() => !props.cart || cartGroups.value.length === 0);

const deliveryApplicable = computed(() => props.cart?.delivery_applicable === true);

const hasAddress = computed(() => localAddress.value.trim().length > 0);

const canSubmit = computed(
    () => hasAddress.value && !props.submitting && !props.savingAddress,
);

function handleAddressFocus() {
    isAddressFocused.value = true;
}

function handleAddressInput() {
    emit('delivery-address-input', localAddress.value);
}

function handleAddressBlur() {
    isAddressFocused.value = false;
    emit('delivery-address-blur', localAddress.value);
}

/** Черновик количества в input до blur/Enter — не шлём API на каждый символ */
function getQuantityDisplay(item) {
    if (focusedQuantityItemId.value === item.key && quantityDrafts.value[item.key] !== undefined) {
        return quantityDrafts.value[item.key];
    }

    return String(item.quantity);
}

function handleQuantityFocus(item) {
    focusedQuantityItemId.value = item.key;
    quantityDrafts.value[item.key] = String(item.quantity);
}

function handleQuantityInput(item, event) {
    quantityDrafts.value[item.key] = event.target.value.replace(/\D/g, '');
}

function clampQuantity(value) {
    return Math.min(MAX_QUANTITY, Math.max(MIN_QUANTITY, value));
}

function commitQuantity(item) {
    focusedQuantityItemId.value = null;

    const raw = quantityDrafts.value[item.key] ?? String(item.quantity);
    delete quantityDrafts.value[item.key];

    const parsed = Number.parseInt(raw, 10);
    const quantity = Number.isNaN(parsed) ? item.quantity : clampQuantity(parsed);

    if (quantity !== item.quantity) {
        emit('update-quantity', item, quantity);
    }
}

function openOrderConfirm() {
    if (!canSubmit.value) {
        return;
    }

    showOrderConfirm.value = true;
}

function closeOrderConfirm() {
    if (!props.submitting) {
        showOrderConfirm.value = false;
    }
}

function confirmOrder() {
    emit('submit-order', localAddress.value);
}

/**
 * Перехват «Назад» из App.vue: сначала закрыть модалку подтверждения.
 * @returns {boolean} true — событие обработано, навигацию не продолжать
 */
function handleBackRequest() {
    if (showOrderConfirm.value) {
        closeOrderConfirm();

        return true;
    }

    return false;
}

function handleGoBack() {
    if (!handleBackRequest()) {
        emit('go-back');
    }
}

defineExpose({ handleBackRequest });

watch(
    () => props.submitting,
    (submitting, wasSubmitting) => {
        if (wasSubmitting && !submitting && props.error) {
            showOrderConfirm.value = false;
        }
    },
);
</script>

<template>
    <div class="flex min-h-dvh flex-col pb-52">
        <header class="sticky top-0 z-10 border-b border-gray-200 bg-white px-4 py-3">
            <div class="flex items-start justify-between gap-3">
                <div class="flex min-w-0 items-start gap-2">
                    <button
                        type="button"
                        class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-gray-600 transition hover:bg-gray-100 disabled:opacity-40"
                        :disabled="loading || submitting"
                        aria-label="Назад"
                        @click="handleGoBack"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <div class="min-w-0">
                        <h1 class="text-lg font-semibold text-gray-900">Корзина</h1>
                        <p v-if="cart?.restaurant_name" class="text-sm text-max-muted">{{ cart.restaurant_name }}</p>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <MyOrdersButton
                        label="Заказы"
                        :unread-count="ordersUnreadCount"
                        button-class="text-sm font-medium text-max-primary transition hover:text-max-primary-hover"
                        @click="emit('open-orders')"
                    />
                    <button
                        v-if="!isEmpty && !loading"
                        type="button"
                        class="text-sm font-medium text-red-500 transition hover:text-red-700 disabled:opacity-40"
                        :disabled="clearing || submitting || savingAddress"
                        @click="emit('clear-cart')"
                    >
                        Очистить
                    </button>
                </div>
            </div>
        </header>

        <main class="flex-1 px-4 py-4">
            <div v-if="loading" class="flex items-center justify-center py-16">
                <div class="h-8 w-8 animate-spin rounded-full border-4 border-max-primary border-t-transparent" />
            </div>

            <div v-else-if="isEmpty" class="flex flex-col items-center py-16 text-center">
                <div class="mb-4 text-5xl">🛒</div>
                <p class="text-base font-medium text-gray-900">Корзина пуста</p>
                <p class="mt-1 text-sm text-max-muted">Добавьте блюда из меню ресторана</p>
                <button
                    type="button"
                    class="mt-6 rounded-2xl bg-max-primary px-6 py-3 text-sm font-medium text-white transition hover:bg-max-primary-hover"
                    @click="emit('go-to-restaurants')"
                >
                    К ресторанам
                </button>
            </div>

            <template v-else>
                <div
                    v-if="error"
                    class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                >
                    {{ error }}
                </div>

                <ul class="space-y-3">
                    <li
                        v-for="item in cartGroups"
                        :key="item.key"
                        class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm"
                    >
                        <div class="flex items-start gap-3">
                            <DishImage
                                :image-url="item.type === 'combo' ? item.items[0]?.image_url : item.item.image_url"
                                :alt="getCartGroupTitle(item)"
                            />
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-gray-900">{{ getCartGroupTitle(item) }}</p>
                                <template v-if="item.type === 'combo'">
                                    <p class="mt-0.5 text-sm text-max-muted">{{ item.quantity }} шт.</p>
                                    <ul class="mt-2 space-y-1 text-xs text-max-muted">
                                        <li
                                            v-for="component in item.items"
                                            :key="component.id"
                                            class="flex justify-between gap-2"
                                        >
                                            <span class="min-w-0 truncate">{{ component.dish_name }}</span>
                                            <span class="shrink-0">{{ component.unit_price }} ₽ × {{ component.quantity }}</span>
                                        </li>
                                    </ul>
                                </template>
                                <p v-else class="mt-0.5 text-sm text-max-muted">
                                    {{ item.item.unit_price }} ₽ × {{ item.quantity }}
                                </p>
                                <p class="mt-1 text-sm font-semibold text-gray-900">{{ item.lineTotal }} ₽</p>
                            </div>
                            <button
                                type="button"
                                class="text-sm text-red-500 transition hover:text-red-700"
                                :disabled="updatingItemId === item.key"
                                @click="emit('remove-item', item)"
                            >
                                Удалить
                            </button>
                        </div>
                        <div class="mt-3 flex items-center gap-3">
                            <button
                                type="button"
                                class="flex h-9 w-9 items-center justify-center rounded-full border border-gray-200 bg-gray-50 text-lg font-medium transition hover:bg-gray-100 disabled:opacity-40"
                                :disabled="item.quantity <= MIN_QUANTITY || updatingItemId === item.key"
                                @click="emit('update-quantity', item, item.quantity - 1)"
                            >
                                −
                            </button>
                            <input
                                type="text"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                autocomplete="off"
                                class="h-9 w-12 rounded-xl border border-gray-200 bg-gray-50 text-center text-sm font-medium text-gray-900 focus:border-max-primary focus:bg-white focus:outline-none focus:ring-1 focus:ring-max-primary disabled:opacity-40"
                                :value="getQuantityDisplay(item)"
                                :disabled="updatingItemId === item.key"
                                :aria-label="`Количество: ${getCartGroupTitle(item)}`"
                                @focus="handleQuantityFocus(item)"
                                @input="handleQuantityInput(item, $event)"
                                @blur="commitQuantity(item)"
                                @keydown.enter.prevent="commitQuantity(item)"
                            />
                            <button
                                type="button"
                                class="flex h-9 w-9 items-center justify-center rounded-full border border-gray-200 bg-gray-50 text-lg font-medium transition hover:bg-gray-100 disabled:opacity-40"
                                :disabled="item.quantity >= MAX_QUANTITY || updatingItemId === item.key"
                                @click="emit('update-quantity', item, item.quantity + 1)"
                            >
                                +
                            </button>
                        </div>
                    </li>
                </ul>

                <section class="mt-6 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                    <label for="delivery-address" class="block text-sm font-medium text-gray-900">
                        Адрес доставки
                    </label>
                    <textarea
                        id="delivery-address"
                        v-model="localAddress"
                        rows="3"
                        maxlength="1000"
                        placeholder="Укажите адрес доставки"
                        class="mt-2 w-full resize-none rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-max-primary focus:bg-white focus:outline-none focus:ring-1 focus:ring-max-primary"
                        @focus="handleAddressFocus"
                        @input="handleAddressInput"
                        @blur="handleAddressBlur"
                    />
                    <p v-if="savingAddress" class="mt-1.5 text-xs text-max-muted">Сохранение адреса…</p>
                    <p v-else-if="!hasAddress" class="mt-1.5 text-xs text-amber-600">
                        Укажите адрес, чтобы оформить заявку
                    </p>
                </section>

            </template>
        </main>

        <div
            v-if="!isEmpty && !loading"
            class="fixed inset-x-0 bottom-0 z-20 border-t border-gray-200 bg-white px-4 py-3 safe-area-bottom"
        >
            <div class="mb-2 space-y-1.5 text-sm">
                <template v-if="deliveryApplicable">
                    <div class="flex items-center justify-between">
                        <span class="text-max-muted">Сумма блюд</span>
                        <span class="font-medium text-gray-900">{{ cart.items_total }} ₽</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-max-muted">Доставка</span>
                        <span class="font-medium text-gray-900">{{ cart.delivery_cost }} ₽</span>
                    </div>
                    <div class="flex items-center justify-between border-t border-gray-100 pt-2 text-base">
                        <span class="font-medium text-gray-900">Итого</span>
                        <span class="text-xl font-bold text-gray-900">{{ cart.total }} ₽</span>
                    </div>
                </template>
                <div v-else class="flex items-center justify-between text-base">
                    <span class="font-medium text-gray-900">Итого</span>
                    <span class="text-xl font-bold text-gray-900">{{ cart.total }} ₽</span>
                </div>
            </div>
            <button
                type="button"
                class="flex w-full items-center justify-center rounded-2xl bg-max-primary px-4 py-3.5 font-medium text-white transition hover:bg-max-primary-hover disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="!canSubmit"
                @click="openOrderConfirm"
            >
                Оформить заявку
            </button>
        </div>

        <div
            v-if="showOrderConfirm"
            class="fixed inset-0 z-30 flex items-end justify-center bg-black/40 px-4 pb-4 pt-8 safe-area-bottom"
            role="dialog"
            aria-modal="true"
            aria-labelledby="order-confirm-title"
            @click.self="closeOrderConfirm"
        >
            <div class="w-full max-w-lg rounded-2xl bg-white p-4 shadow-xl">
                <h2 id="order-confirm-title" class="text-lg font-semibold text-gray-900">
                    Подтвердите заявку
                </h2>
                <p class="mt-1 text-sm text-max-muted">
                    Проверьте состав заказа и адрес доставки перед отправкой
                </p>

                <div class="mt-4 max-h-48 overflow-y-auto rounded-xl border border-gray-100 bg-gray-50 p-3">
                    <ul class="space-y-2 text-sm">
                        <li
                            v-for="item in cartGroups"
                            :key="item.key"
                            class="flex items-center justify-between gap-3"
                        >
                            <span class="min-w-0 text-gray-700">{{ getCartGroupTitle(item) }} × {{ item.quantity }}</span>
                            <span class="shrink-0 font-medium text-gray-900">{{ item.lineTotal }} ₽</span>
                        </li>
                    </ul>
                </div>

                <div class="mt-4 rounded-xl border border-gray-100 bg-gray-50 p-3 text-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-max-muted">Адрес доставки</p>
                    <p class="mt-1 text-gray-900">{{ localAddress.trim() }}</p>
                </div>

                <div class="mt-4 space-y-1.5 text-sm">
                    <template v-if="deliveryApplicable">
                        <div class="flex items-center justify-between">
                            <span class="text-max-muted">Сумма блюд</span>
                            <span class="font-medium text-gray-900">{{ cart.items_total }} ₽</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-max-muted">Доставка</span>
                            <span class="font-medium text-gray-900">{{ cart.delivery_cost }} ₽</span>
                        </div>
                    </template>
                    <div class="flex items-center justify-between border-t border-gray-200 pt-2 text-base">
                        <span class="font-medium text-gray-900">Итого</span>
                        <span class="text-lg font-bold text-gray-900">{{ cart.total }} ₽</span>
                    </div>
                </div>

                <div class="mt-5 flex gap-3">
                    <button
                        type="button"
                        class="flex-1 rounded-2xl border border-gray-200 bg-white px-4 py-3 font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-40"
                        :disabled="submitting"
                        @click="closeOrderConfirm"
                    >
                        Отмена
                    </button>
                    <button
                        type="button"
                        class="flex flex-1 items-center justify-center rounded-2xl bg-max-primary px-4 py-3 font-medium text-white transition hover:bg-max-primary-hover disabled:opacity-60"
                        :disabled="submitting"
                        @click="confirmOrder"
                    >
                        <span
                            v-if="submitting"
                            class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"
                        />
                        Подтвердить
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
