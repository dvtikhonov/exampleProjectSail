<script setup>
/**
 * Панель сборки комбо из двух блюд — сразу под шапкой меню.
 */
defineProps({
    open: {
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
    comboQuantity: {
        type: Number,
        default: 1,
    },
    comboTotal: {
        type: String,
        default: '0.00',
    },
    canAddCombo: {
        type: Boolean,
        default: false,
    },
    addingComboRef: {
        type: String,
        default: null,
    },
});

defineEmits(['close', 'reset-second', 'change-quantity', 'add-combo']);
</script>

<template>
    <section
        v-if="open"
        class="border-b border-gray-200 bg-white px-4 pb-4 pt-3 shadow-md"
        role="dialog"
        aria-modal="true"
        aria-labelledby="combo-builder-title"
    >
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 id="combo-builder-title" class="text-base font-semibold text-gray-900">
                    Собрать блюдо
                </h2>
                <p class="mt-1 text-sm text-max-muted">
                    Выберите второе блюдо из другой категории
                </p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <button
                    v-if="comboSecondDish"
                    type="button"
                    class="text-sm font-medium text-max-primary"
                    @click="$emit('reset-second')"
                >
                    Сбросить
                </button>
                <button
                    type="button"
                    class="text-sm font-medium text-max-muted hover:text-gray-700"
                    @click="$emit('close')"
                >
                    Закрыть
                </button>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-2 text-sm">
            <div class="rounded-xl bg-gray-50 p-3">
                <p class="text-xs font-medium uppercase tracking-wide text-max-muted">Блюдо 1</p>
                <p class="mt-1 font-medium text-gray-900">
                    {{ comboFirstDish?.name ?? 'Не выбрано' }}
                </p>
                <p v-if="comboFirstDish" class="mt-0.5 text-xs text-max-muted">
                    {{ comboFirstDish.category_name }}
                </p>
            </div>
            <div class="rounded-xl bg-gray-50 p-3">
                <p class="text-xs font-medium uppercase tracking-wide text-max-muted">Блюдо 2</p>
                <p class="mt-1 font-medium text-gray-900">
                    {{ comboSecondDish?.name ?? 'Не выбрано' }}
                </p>
                <p v-if="comboSecondDish" class="mt-0.5 text-xs text-max-muted">
                    {{ comboSecondDish.category_name }}
                </p>
            </div>
        </div>

        <div class="mt-4 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="flex h-9 w-9 items-center justify-center rounded-full border border-gray-200 bg-gray-50 text-lg font-medium transition hover:bg-gray-100 disabled:opacity-40"
                    :disabled="comboQuantity <= 1 || addingComboRef !== null"
                    @click="$emit('change-quantity', -1)"
                >
                    −
                </button>
                <span class="w-8 text-center text-sm font-semibold text-gray-900">{{ comboQuantity }}</span>
                <button
                    type="button"
                    class="flex h-9 w-9 items-center justify-center rounded-full border border-gray-200 bg-gray-50 text-lg font-medium transition hover:bg-gray-100 disabled:opacity-40"
                    :disabled="comboQuantity >= 99 || addingComboRef !== null"
                    @click="$emit('change-quantity', 1)"
                >
                    +
                </button>
            </div>
            <button
                type="button"
                class="flex min-w-40 items-center justify-center rounded-2xl bg-max-primary px-4 py-3 text-sm font-medium text-white transition hover:bg-max-primary-hover disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!canAddCombo"
                @click="$emit('add-combo')"
            >
                <span
                    v-if="addingComboRef"
                    class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"
                />
                Добавить · {{ comboTotal }} ₽
            </button>
        </div>
    </section>
</template>
