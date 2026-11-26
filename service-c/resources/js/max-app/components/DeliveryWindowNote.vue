<script setup>
/**
 * Спокойная заметка об окне доставки. Общая для меню, корзины и карточки заказа.
 */
import { computed } from 'vue';
import { formatDeliveryWindowCaption } from '../utils/formatIsoDateRu';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps({
    deliveryDate: {
        type: String,
        default: '',
    },
});

const caption = computed(() => formatDeliveryWindowCaption(props.deliveryDate));
</script>

<template>
    <p
        v-if="caption"
        v-bind="$attrs"
        class="rounded-xl bg-amber-50 px-3 py-2 text-sm font-medium leading-5 text-amber-950"
    >
        {{ caption }}
    </p>
</template>
