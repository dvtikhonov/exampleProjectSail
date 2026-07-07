<script setup>
/**
 * Строка состава заказа из items_snapshot с пометкой комбо.
 */
import { computed } from 'vue';
import DishImage from './DishImage.vue';
import { getComboPartnerName, isComboSnapshotItem } from '../utils/orderSnapshotCombo';

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
    itemsSnapshot: {
        type: Array,
        required: true,
    },
});

const isComboItem = computed(() => isComboSnapshotItem(props.item));
const comboPartnerName = computed(() => getComboPartnerName(props.item, props.itemsSnapshot));
</script>

<template>
    <li class="flex items-center gap-3 text-sm">
        <DishImage :image-url="item.image_url" :alt="item.dish_name" size="sm" />
        <div class="min-w-0 flex-1">
            <p class="text-gray-700">{{ item.dish_name }} × {{ item.quantity }}</p>
            <p v-if="isComboItem" class="mt-0.5 text-xs text-max-primary">
                <template v-if="comboPartnerName">Входит в комбо: {{ comboPartnerName }}</template>
                <template v-else>Входит в комбо</template>
            </p>
        </div>
        <span class="shrink-0 font-medium text-gray-900">{{ item.line_total }} ₽</span>
    </li>
</template>
