<script setup>
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { ref, watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    columns: {
        type: Array,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['apply', 'clear', 'close']);
const localFilters = ref({ ...props.filters });

watch(
    () => props.filters,
    (filters) => {
        localFilters.value = { ...filters };
    },
);

watch(
    () => props.show,
    (show) => {
        if (show) {
            localFilters.value = { ...props.filters };
        }
    },
);

const updateFilter = (columnKey, value) => {
    localFilters.value = {
        ...localFilters.value,
        [columnKey]: value,
    };
};

const normalizedFilters = () =>
    Object.fromEntries(
        Object.entries(localFilters.value).filter(([, value]) => value.trim() !== ''),
    );

const apply = () => {
    emit('apply', normalizedFilters());
};

const clear = () => {
    localFilters.value = {};
    emit('clear');
};
</script>

<template>
    <Modal :show="show" max-width="lg" @close="emit('close')">
        <div class="p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">
                        Фильтры таблицы
                    </h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Заполните поля колонок, по которым нужно отфильтровать объекты продаж.
                    </p>
                </div>
                <button
                    type="button"
                    class="text-2xl leading-none text-gray-400 hover:text-gray-600"
                    @click="emit('close')"
                >
                    ×
                </button>
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <label
                    v-for="column in columns"
                    :key="column.key"
                    class="block text-sm font-medium text-gray-700"
                >
                    <span>{{ column.label }}</span>
                    <input
                        type="text"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        :value="localFilters[column.key] ?? ''"
                        @input="updateFilter(column.key, $event.target.value)"
                        @keydown.enter.prevent="apply"
                    />
                </label>
            </div>

            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <SecondaryButton type="button" @click="clear">
                    Сбросить
                </SecondaryButton>
                <SecondaryButton type="button" @click="emit('close')">
                    Отмена
                </SecondaryButton>
                <PrimaryButton type="button" @click="apply">
                    Применить
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
