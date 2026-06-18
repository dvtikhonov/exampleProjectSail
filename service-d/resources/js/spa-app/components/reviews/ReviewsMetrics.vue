<script setup>
/** Сводка метрик организации над таблицей отзывов. */
import MetricCard from '../ui/MetricCard.vue';
import { formatCount, formatRating } from '../../utils/formatters';

defineProps({
    metrics: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <div class="mt-8 grid gap-4 sm:grid-cols-3">
        <MetricCard
            label="Средний рейтинг"
            :value="formatRating(metrics.average_rating)"
        />
        <MetricCard
            label="Оценок"
            :value="formatCount(metrics.ratings_count)"
        />
        <MetricCard
            label="Отзывов"
            :value="formatCount(metrics.reviews_count)"
        />
    </div>
</template>
