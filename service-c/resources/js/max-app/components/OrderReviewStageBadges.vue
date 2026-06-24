<script setup>
/**
 * Пара бейджей этапов проверки: адрес и состав заказа (для админ-списка и карточки).
 */
import { computed } from 'vue';
import { getReviewStageDisplay } from '../utils/orderStatus';

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
});

const stages = computed(() => [
    getReviewStageDisplay('address', props.order.address_review_status),
    getReviewStageDisplay('composition', props.order.composition_review_status),
]);
</script>

<template>
    <div class="flex flex-wrap gap-1.5">
        <span
            v-for="stage in stages"
            :key="stage.label"
            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
            :class="stage.badgeClass"
        >
            {{ stage.label }}
        </span>
    </div>
</template>
