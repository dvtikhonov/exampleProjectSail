<script setup>
/**
 * Список позиций корзины: группы, количество, удаление.
 */
import { ref } from 'vue';
import DishImage from '../DishImage.vue';
import { getCartGroupTitle } from '../../utils/cartGroups';
import { formatDishWeight } from '../../utils/dishWeight';

defineProps({
    cartGroups: {
        type: Array,
        default: () => [],
    },
    updatingItemId: {
        type: [Number, String],
        default: null,
    },
});

const emit = defineEmits(['update-quantity', 'remove-item']);

const MIN_QUANTITY = 1;
const MAX_QUANTITY = 99;

const quantityDrafts = ref({});
const focusedQuantityItemId = ref(null);

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
</script>

<template>
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
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900">{{ getCartGroupTitle(item) }}</p>
                            <p
                                v-if="item.type !== 'combo' && formatDishWeight(item.item)"
                                class="mt-0.5 text-sm text-max-muted"
                            >
                                {{ formatDishWeight(item.item) }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="shrink-0 text-sm text-red-500 transition hover:text-red-700"
                            :disabled="updatingItemId === item.key"
                            @click="emit('remove-item', item)"
                        >
                            Удалить
                        </button>
                    </div>

                    <ul
                        v-if="item.type === 'combo'"
                        class="mt-2 space-y-1 text-xs text-max-muted"
                    >
                        <li
                            v-for="component in item.items"
                            :key="component.id"
                        >
                            <p class="truncate text-xs text-max-muted">{{ component.dish_name }}</p>
                            <p
                                v-if="formatDishWeight(component)"
                                class="text-xs text-max-muted"
                            >
                                {{ formatDishWeight(component) }}
                            </p>
                        </li>
                    </ul>

                    <div class="mt-3 flex items-end justify-between gap-3">
                        <div class="flex flex-col gap-2">
                            <p class="text-sm font-medium text-max-primary">
                                Изменить
                            </p>
                            <div class="flex items-center gap-3">
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
                        </div>

                        <div class="flex shrink-0 flex-col items-end text-sm">
                            <span
                                v-if="item.type !== 'combo'"
                                class="text-max-muted"
                            >
                                {{ item.item.unit_price }} ₽ × {{ item.quantity }}
                            </span>
                            <span class="font-semibold text-gray-900">{{ item.lineTotal }} ₽</span>
                        </div>
                    </div>
                </div>
            </div>
        </li>
    </ul>
</template>
