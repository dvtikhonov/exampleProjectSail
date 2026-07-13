<script setup lang="ts">
import type { SortDirection, TaskSortField, TaskStatus, TaskToolbarFilters } from '~/types/task';
import { TASK_SORT_OPTIONS, TASK_STATUS_OPTIONS } from '~/types/task';

const props = defineProps<{
    modelValue: TaskToolbarFilters;
    disabled?: boolean;
    loading?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: TaskToolbarFilters];
}>();

const localSearch = ref(props.modelValue.search);

let searchDebounceTimer: ReturnType<typeof setTimeout> | undefined;

watch(
    () => props.modelValue.search,
    (value) => {
        if (searchDebounceTimer) {
            return;
        }

        if (value !== localSearch.value) {
            localSearch.value = value;
        }
    },
);

/** Обновляет фильтры и пробрасывает их наверх. */
function patchFilters(patch: Partial<TaskToolbarFilters>): void {
    emit('update:modelValue', {
        ...props.modelValue,
        ...patch,
    });
}

/** Debounce 300ms для поля поиска. */
function onSearchInput(value: string): void {
    localSearch.value = value;

    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }

    searchDebounceTimer = setTimeout(() => {
        searchDebounceTimer = undefined;
        patchFilters({ search: value });
    }, 300);
}

onBeforeUnmount(() => {
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }
});
</script>

<template>
    <div class="space-y-4 rounded-xl border border-slate-800 bg-slate-900/60 p-4">
        <div
            v-if="loading"
            class="flex items-center gap-2 text-sm text-slate-400"
            aria-live="polite"
        >
            <AppSpinner size="sm" />
            <span>Обновление списка…</span>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="space-y-1 sm:col-span-2 lg:col-span-2">
                <label
                    for="task-search"
                    class="block text-sm font-medium text-slate-300"
                >
                    Поиск
                </label>
                <input
                    id="task-search"
                    :value="localSearch"
                    type="search"
                    placeholder="Название или описание…"
                    class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white outline-none ring-indigo-500 focus:ring-2 disabled:opacity-60"
                    @input="onSearchInput(($event.target as HTMLInputElement).value)"
                >
            </div>

            <div class="space-y-1">
                <label
                    for="task-status"
                    class="block text-sm font-medium text-slate-300"
                >
                    Статус
                </label>
                <select
                    id="task-status"
                    :value="modelValue.status"
                    :disabled="disabled"
                    class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white outline-none ring-indigo-500 focus:ring-2 disabled:opacity-60"
                    @change="patchFilters({ status: ($event.target as HTMLSelectElement).value as TaskStatus | '' })"
                >
                    <option value="">
                        Все
                    </option>
                    <option
                        v-for="option in TASK_STATUS_OPTIONS"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </div>

            <div class="space-y-1">
                <label
                    for="task-sort"
                    class="block text-sm font-medium text-slate-300"
                >
                    Сортировка
                </label>
                <select
                    id="task-sort"
                    :value="modelValue.sort"
                    :disabled="disabled"
                    class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white outline-none ring-indigo-500 focus:ring-2 disabled:opacity-60"
                    @change="patchFilters({ sort: ($event.target as HTMLSelectElement).value as TaskSortField })"
                >
                    <option
                        v-for="option in TASK_SORT_OPTIONS"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </div>
        </div>

        <div class="flex w-full flex-wrap items-center justify-end gap-3">
            <span class="text-sm text-slate-400">Направление:</span>
            <div class="inline-flex rounded-lg border border-slate-700 p-0.5">
                <button
                    type="button"
                    :disabled="disabled"
                    class="rounded-md px-3 py-1.5 text-sm transition disabled:opacity-60"
                    :class="modelValue.direction === 'desc' ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:text-white'"
                    @click="patchFilters({ direction: 'desc' })"
                >
                    По убыванию
                </button>
                <button
                    type="button"
                    :disabled="disabled"
                    class="rounded-md px-3 py-1.5 text-sm transition disabled:opacity-60"
                    :class="modelValue.direction === 'asc' ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:text-white'"
                    @click="patchFilters({ direction: 'asc' })"
                >
                    По возрастанию
                </button>
            </div>
        </div>
    </div>
</template>
