<script setup>
/**
 * Бейдж агрегированного статуса заказа (Поступил, На проверке, Выполнен и т.д.).
 *
 * @typedef {import('../api/types.js').OrderDto} OrderDto
 * @typedef {import('../api/types.js').AdminOrderListItemDto} AdminOrderListItemDto
 * @typedef {import('../api/types.js').AdminOrderDetailDto} AdminOrderDetailDto
 */
import { computed } from 'vue';
import { getOrderStatusDisplay } from '../utils/orderStatus';

const props = defineProps({
    /** Заказ клиента или админ-очереди */
    order: {
        type: Object,
        required: true,
    },
    size: {
        type: String,
        default: 'sm',
        validator: (value) => ['sm', 'md'].includes(value),
    },
});

const display = computed(() => getOrderStatusDisplay(props.order));

const sizeClass = computed(() =>
    props.size === 'md' ? 'px-3 py-1 text-sm' : 'px-2 py-0.5 text-xs',
);
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center rounded-full font-medium"
        :class="[display.badgeClass, sizeClass]"
    >
        {{ display.label }}
    </span>
</template>
