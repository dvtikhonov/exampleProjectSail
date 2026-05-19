<script setup>
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
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 px-4 py-6 backdrop-blur-sm sm:px-0"
            @click.self="emit('close')"
        >
            <div class="mx-auto w-full max-w-lg overflow-hidden rounded-2xl border border-slate-700 bg-slate-900 shadow-2xl shadow-black/50">
                <div class="p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-white">
                                Фильтры таблицы
                            </h3>
                            <p class="mt-1 text-sm text-slate-400">
                                Заполните поля колонок, по которым нужно отфильтровать объекты продаж.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="text-2xl leading-none text-slate-500 transition hover:text-white"
                            @click="emit('close')"
                        >
                            ×
                        </button>
                    </div>

                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                        <label
                            v-for="column in columns"
                            :key="column.key"
                            class="block text-sm font-medium text-slate-200"
                        >
                            <span>{{ column.label }}</span>
                            <input
                                type="text"
                                class="mt-1 block w-full rounded-md border-slate-700 bg-slate-950 text-sm text-slate-100 shadow-sm placeholder:text-slate-500 focus:border-cyan-400 focus:ring-cyan-400"
                                :value="localFilters[column.key] ?? ''"
                                @input="updateFilter(column.key, $event.target.value)"
                                @keydown.enter.prevent="apply"
                            />
                        </label>
                    </div>

                    <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg border border-slate-700 bg-slate-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-100 transition hover:border-cyan-400"
                            @click="clear"
                        >
                            Сбросить
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg border border-slate-700 bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-300 transition hover:text-white"
                            @click="emit('close')"
                        >
                            Отмена
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg border border-transparent bg-cyan-500 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-950 transition hover:bg-cyan-400"
                            @click="apply"
                        >
                            Применить
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
