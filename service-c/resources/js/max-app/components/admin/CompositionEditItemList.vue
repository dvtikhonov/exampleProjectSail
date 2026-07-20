<script setup>
/**
 * Редактируемый список позиций состава заказа (draft): количество и удаление.
 */
import { ref } from 'vue';
import DishImage from '../DishImage.vue';
import { getSnapshotGroupTitle } from '../../utils/orderSnapshotGroups';

defineProps({
    groups: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['update-quantity', 'remove-group']);

const MIN_QUANTITY = 1;
const MAX_QUANTITY = 99;

const quantityDrafts = ref({});
const focusedQuantityKey = ref(null);

function getQuantityDisplay(group) {
    if (focusedQuantityKey.value === group.key && quantityDrafts.value[group.key] !== undefined) {
        return quantityDrafts.value[group.key];
    }

    return String(group.quantity);
}

function handleQuantityFocus(group) {
    focusedQuantityKey.value = group.key;
    quantityDrafts.value[group.key] = String(group.quantity);
}

function handleQuantityInput(group, event) {
    quantityDrafts.value[group.key] = event.target.value.replace(/\D/g, '');
}

function clampQuantity(value) {
    return Math.min(MAX_QUANTITY, Math.max(MIN_QUANTITY, value));
}

function commitQuantity(group) {
    focusedQuantityKey.value = null;

    const raw = quantityDrafts.value[group.key] ?? String(group.quantity);
    delete quantityDrafts.value[group.key];

    const parsed = Number.parseInt(raw, 10);
    const quantity = Number.isNaN(parsed) ? group.quantity : clampQuantity(parsed);

    if (quantity !== group.quantity) {
        emit('update-quantity', group, quantity);
    }
}
</script>

<template>
    <ul class="space-y-3">
        <li
            v-for="group in groups"
            :key="group.key"
            class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm"
        >
            <div class="flex items-start gap-3">
                <DishImage
                    :image-url="group.type === 'combo' ? group.items[0]?.image_url : group.item.image_url"
                    :alt="getSnapshotGroupTitle(group)"
                />
                <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-medium text-gray-900">{{ getSnapshotGroupTitle(group) }}</p>
                        <button
                            type="button"
                            class="shrink-0 text-sm text-red-500 transition hover:text-red-700"
                            @click="emit('remove-group', group)"
                        >
                            Удалить
                        </button>
                    </div>

                    <ul
                        v-if="group.type === 'combo'"
                        class="mt-2 space-y-1 text-xs text-max-muted"
                    >
                        <li
                            v-for="component in group.items"
                            :key="component.dish_id"
                        >
                            <p class="truncate">{{ component.dish_name }}</p>
                        </li>
                    </ul>

                    <div class="mt-3 flex items-end justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                class="flex h-9 w-9 items-center justify-center rounded-full border border-gray-200 bg-gray-50 text-lg font-medium transition hover:bg-gray-100 disabled:opacity-40"
                                :disabled="group.quantity <= MIN_QUANTITY"
                                @click="emit('update-quantity', group, group.quantity - 1)"
                            >
                                −
                            </button>
                            <input
                                type="text"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                autocomplete="off"
                                class="h-9 w-12 rounded-xl border border-gray-200 bg-gray-50 text-center text-sm font-medium text-gray-900 focus:border-max-primary focus:bg-white focus:outline-none focus:ring-1 focus:ring-max-primary"
                                :value="getQuantityDisplay(group)"
                                :aria-label="`Количество: ${getSnapshotGroupTitle(group)}`"
                                @focus="handleQuantityFocus(group)"
                                @input="handleQuantityInput(group, $event)"
                                @blur="commitQuantity(group)"
                                @keydown.enter.prevent="commitQuantity(group)"
                            />
                            <button
                                type="button"
                                class="flex h-9 w-9 items-center justify-center rounded-full border border-gray-200 bg-gray-50 text-lg font-medium transition hover:bg-gray-100 disabled:opacity-40"
                                :disabled="group.quantity >= MAX_QUANTITY"
                                @click="emit('update-quantity', group, group.quantity + 1)"
                            >
                                +
                            </button>
                        </div>

                        <span class="shrink-0 text-sm font-semibold text-gray-900">{{ group.lineTotal }} ₽</span>
                    </div>
                </div>
            </div>
        </li>
    </ul>
</template>
