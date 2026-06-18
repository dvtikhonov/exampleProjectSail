<script setup>
/**
 * Таблица отзывов организации (одна страница API).
 */
import { computed } from 'vue';
import { formatDate } from '../../utils/formatters';

const props = defineProps({
    reviews: {
        type: Array,
        required: true,
    },
    pagination: {
        type: Object,
        default: null,
    },
});

const wrapperClass = computed(() => [
    'overflow-x-auto rounded-xl border border-slate-200',
    props.pagination && props.pagination.last_page > 1 ? 'mt-4' : 'mt-8',
]);
</script>

<template>
    <div :class="wrapperClass">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th
                        scope="col"
                        class="px-4 py-3 text-left font-medium text-slate-600"
                    >
                        Автор
                    </th>
                    <th
                        scope="col"
                        class="px-4 py-3 text-left font-medium text-slate-600"
                    >
                        Дата
                    </th>
                    <th
                        scope="col"
                        class="px-4 py-3 text-left font-medium text-slate-600"
                    >
                        Оценка
                    </th>
                    <th
                        scope="col"
                        class="px-4 py-3 text-left font-medium text-slate-600"
                    >
                        Текст
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                <tr
                    v-for="review in reviews"
                    :key="review.id"
                    class="align-top"
                >
                    <td class="whitespace-nowrap px-4 py-3 font-medium text-slate-900">
                        {{ review.author_name }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-slate-600">
                        {{ formatDate(review.published_at) }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-slate-900">
                        {{ review.rating ?? '—' }}
                    </td>
                    <td class="px-4 py-3 text-slate-700">
                        <span v-if="review.text">{{ review.text }}</span>
                        <span
                            v-else
                            class="italic text-slate-400"
                        >Без текста</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
