<script setup>
/**
 * Карточка блюда в сетке меню.
 */
import { computed } from 'vue';
import DishImage from '../DishImage.vue';

const props = defineProps({
    dish: {
        type: Object,
        required: true,
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

const emit = defineEmits(['add-to-cart', 'start-combo', 'select-second-combo-dish']);

const isAdding = computed(() => props.addingDishId === props.dish.id);

const isSelectedAsSecond = computed(() => props.comboSecondDish?.id === props.dish.id);

const canSelectAsSecondDish = computed(() => {
    if (!props.comboBuilderOpen || !props.comboFirstDish || props.addingComboRef !== null) {
        return false;
    }

    if (props.comboFirstDish.category_id === props.dish.category_id) {
        return false;
    }

    return props.comboFirstDish.id !== props.dish.id;
});

const showComboBuilderAction = computed(
    () => props.dish.is_combo_available && !props.comboBuilderOpen,
);

const priceLabel = computed(() => {
    const prefix = props.dish.is_combo_available ? 'от ' : '';

    return `${prefix}${props.dish.price} ₽`;
});

function handlePriceClick() {
    if (isAdding.value || props.addingComboRef !== null) {
        return;
    }

    emit('add-to-cart', props.dish);
}

function handleStartCombo() {
    if (props.addingComboRef !== null) {
        return;
    }

    emit('start-combo', props.dish);
}

function handleSelectSecond() {
    if (!canSelectAsSecondDish.value) {
        return;
    }

    emit('select-second-combo-dish', props.dish);
}
</script>

<template>
    <article
        class="flex flex-col overflow-hidden rounded-[var(--radius-menu-card)] bg-menu-card p-2"
        :class="canSelectAsSecondDish && 'ring-2 ring-max-primary/30'"
    >
        <div class="mb-2 flex justify-center">
            <DishImage
                :image-url="dish.image_url"
                :alt="dish.name"
                size="lg"
            />
        </div>

        <h3 class="mb-2 line-clamp-2 min-h-[2rem] text-xs font-medium leading-tight text-max-text">
            {{ dish.name }}
        </h3>

        <div class="mt-auto space-y-1">
            <button
                v-if="canSelectAsSecondDish"
                type="button"
                class="flex w-full items-center justify-center rounded-full border border-max-primary bg-white px-2 py-1.5 text-xs font-medium text-max-primary transition hover:bg-max-primary/10 disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="addingComboRef !== null"
                @click="handleSelectSecond"
            >
                {{ isSelectedAsSecond ? 'Выбрано' : 'В комбо' }}
            </button>

            <button
                type="button"
                class="flex w-full items-center justify-center rounded-full bg-max-text px-2 py-1.5 text-xs font-medium text-white transition hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="isAdding || addingComboRef !== null"
                @click="handlePriceClick"
            >
                <span
                    v-if="isAdding"
                    class="mr-1.5 h-3 w-3 animate-spin rounded-full border-2 border-white border-t-transparent"
                />
                {{ priceLabel }}
            </button>

            <button
                v-if="showComboBuilderAction"
                type="button"
                class="w-full text-center text-[10px] font-medium leading-tight text-max-primary transition hover:text-max-primary-hover disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="addingComboRef !== null"
                @click="handleStartCombo"
            >
                собрать комбо
            </button>
        </div>
    </article>
</template>
