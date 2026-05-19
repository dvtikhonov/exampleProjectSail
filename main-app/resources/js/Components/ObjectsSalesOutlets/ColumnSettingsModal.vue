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
    selectedColumns: {
        type: Array,
        required: true,
    },
});

const emit = defineEmits(['close', 'apply']);
const localColumns = ref([...props.selectedColumns]);

watch(
    () => props.selectedColumns,
    (columns) => {
        localColumns.value = [...columns];
    },
);

const toggleColumn = (columnKey) => {
    if (localColumns.value.includes(columnKey)) {
        localColumns.value = localColumns.value.filter((key) => key !== columnKey);
        return;
    }

    localColumns.value = [...localColumns.value, columnKey];
};

const selectAll = () => {
    localColumns.value = props.columns.map((column) => column.key);
};

const apply = () => {
    const columns = localColumns.value.length
        ? localColumns.value
        : props.columns.map((column) => column.key);

    emit('apply', columns);
};
</script>

<template>
    <Modal :show="show" max-width="lg" @close="emit('close')">
        <div class="p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">
                        Настройка таблицы
                    </h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Выберите колонки, которые должны быть видны в списке объектов продаж.
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

            <div class="mt-6 grid gap-3 sm:grid-cols-2">
                <label
                    v-for="column in columns"
                    :key="column.key"
                    class="flex cursor-pointer items-center gap-3 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                >
                    <input
                        type="checkbox"
                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                        :checked="localColumns.includes(column.key)"
                        @change="toggleColumn(column.key)"
                    />
                    <span>{{ column.label }}</span>
                </label>
            </div>

            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <SecondaryButton type="button" @click="selectAll">
                    Выбрать все
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
