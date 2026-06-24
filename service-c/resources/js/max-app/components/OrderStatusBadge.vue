<script setup>
import { computed } from 'vue';
import { getOrderStatusDisplay } from '../utils/orderStatus';

const props = defineProps({
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
