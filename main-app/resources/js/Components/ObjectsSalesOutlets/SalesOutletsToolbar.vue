<script setup>
import SecondaryButton from '@/Components/SecondaryButton.vue';

defineProps({
    hasActiveFilters: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['open-columns', 'open-filters']);
</script>

<template>
    <div
        class="flex flex-col gap-3 border-b border-gray-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between"
    >
        <div class="text-sm text-gray-500">
            Управление таблицей объектов продаж
        </div>

        <div class="flex flex-wrap gap-2">
            <SecondaryButton type="button" @click="emit('open-columns')">
                Настройка таблицы
            </SecondaryButton>
            <SecondaryButton type="button" @click="emit('open-filters')">
                Фильтры
                <span
                    v-if="hasActiveFilters"
                    class="ml-1 rounded-full bg-indigo-100 px-1.5 py-0.5 text-[10px] font-bold text-indigo-700"
                >
                    ON
                </span>
            </SecondaryButton>
            <button
                type="button"
                class="inline-flex items-center rounded-md border border-transparent bg-amber-500 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2"
            >
                Принять все изменения
            </button>
            <button
                type="button"
                class="inline-flex items-center rounded-md border border-transparent bg-orange-500 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-orange-600 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2"
            >
                Сменить потенциал
            </button>
        </div>
    </div>
</template>
