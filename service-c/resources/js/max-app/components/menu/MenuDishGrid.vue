<script setup>
/**
 * Сетка карточек блюд (2 колонки).
 */
import MenuDishCard from './MenuDishCard.vue';

defineProps({
    dishes: {
        type: Array,
        default: () => [],
    },
    addingDishId: {
        type: Number,
        default: null,
    },
    addingComboRef: {
        type: String,
        default: null,
    },
    comboBuilderOpen: {
        type: Boolean,
        default: false,
    },
    comboFirstDish: {
        type: Object,
        default: null,
    },
    comboSecondDish: {
        type: Object,
        default: null,
    },
});

defineEmits(['add-to-cart', 'start-combo', 'select-second-combo-dish']);
</script>

<template>
    <div class="grid grid-cols-2 gap-3 px-4">
        <MenuDishCard
            v-for="dish in dishes"
            :key="dish.id"
            :dish="dish"
            :adding-dish-id="addingDishId"
            :adding-combo-ref="addingComboRef"
            :combo-builder-open="comboBuilderOpen"
            :combo-first-dish="comboFirstDish"
            :combo-second-dish="comboSecondDish"
            @add-to-cart="$emit('add-to-cart', $event)"
            @start-combo="$emit('start-combo', $event)"
            @select-second-combo-dish="$emit('select-second-combo-dish', $event)"
        />
    </div>
</template>
