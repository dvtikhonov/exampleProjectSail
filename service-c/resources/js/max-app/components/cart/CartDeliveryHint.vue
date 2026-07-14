<script setup>
/**
 * Подсказка о следующем пороге доставки в footer корзины.
 * Показывается только при delivery_applicable и наличии next tier в ответе API.
 */
import { computed } from 'vue';

const props = defineProps({
    cart: {
        type: Object,
        required: true,
    },
    deliveryApplicable: {
        type: Boolean,
        default: false,
    },
});

const showHint = computed(
    () =>
        props.deliveryApplicable
        && props.cart.amount_to_next_tier != null
        && props.cart.next_tier_delivery_cost != null,
);

const nextTierDeliveryLabel = computed(() => {
    if (props.cart.next_tier_delivery_cost === '0.00') {
        return 'бесплатно';
    }

    return `за ${props.cart.next_tier_delivery_cost} ₽`;
});
</script>

<template>
    <div
        v-if="showHint"
        class="rounded-xl bg-max-primary/10 px-3 py-2 text-sm text-gray-800"
    >
        Доставка {{ cart.delivery_cost }} ₽. Ещё {{ cart.amount_to_next_tier }} ₽, и доставим
        {{ nextTierDeliveryLabel }}.
    </div>
</template>
