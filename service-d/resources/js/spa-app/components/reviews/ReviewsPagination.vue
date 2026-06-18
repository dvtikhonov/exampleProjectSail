<script setup>
/**
 * Пагинация отзывов (только «Назад» / «Вперёд»).
 *
 * position — top/bottom для отступов; page-change — номер целевой страницы.
 */
import { computed } from 'vue';
import { formatCount } from '../../utils/formatters';

const props = defineProps({
    pagination: {
        type: Object,
        required: true,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    position: {
        type: String,
        default: 'top',
        validator: (value) => ['top', 'bottom'].includes(value),
    },
});

const emit = defineEmits(['page-change']);

const wrapperClass = computed(() => [
    'flex flex-wrap items-center justify-between gap-4',
    props.position === 'top' ? 'mt-8' : 'mt-6',
]);

function goToPrevious() {
    emit('page-change', props.pagination.current_page - 1);
}

function goToNext() {
    emit('page-change', props.pagination.current_page + 1);
}
</script>

<template>
    <div :class="wrapperClass">
        <p class="text-sm text-slate-600">
            Страница {{ pagination.current_page }} из {{ pagination.last_page }}
            <span class="text-slate-400">
                · {{ formatCount(pagination.total) }} записей
            </span>
        </p>
        <div class="flex gap-2">
            <button
                type="button"
                :disabled="loading || pagination.current_page <= 1"
                class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                @click="goToPrevious"
            >
                Назад
            </button>
            <button
                type="button"
                :disabled="loading || pagination.current_page >= pagination.last_page"
                class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                @click="goToNext"
            >
                Вперёд
            </button>
        </div>
    </div>
</template>
