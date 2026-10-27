<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    search: {
        type: String,
        default: '',
    },
    status: {
        type: String,
        default: '',
    },
    statusOptions: {
        type: Array,
        default: () => [],
    },
    perPage: {
        type: Number,
        default: 15,
    },
    isLoading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['apply']);

const localSearch = ref(props.search ?? '');
const localStatus = ref(props.status ?? '');
const localPerPage = ref(props.perPage ?? 15);

watch(
    () => [props.search, props.status, props.perPage],
    ([search, status, perPage]) => {
        localSearch.value = search ?? '';
        localStatus.value = status ?? '';
        localPerPage.value = perPage ?? 15;
    },
);

const apply = () => {
    emit('apply', {
        search: String(localSearch.value ?? '').trim(),
        status: localStatus.value || '',
        per_page: Number(localPerPage.value) || 15,
    });
};
</script>

<template>
  <div class="flex flex-col gap-3 border-b border-slate-800 bg-slate-950/60 p-4 lg:flex-row lg:items-end lg:justify-between">
    <div class="grid flex-1 gap-3 sm:grid-cols-3">
      <label class="block text-sm font-medium text-slate-200">
        <span>Поиск</span>
        <input
          v-model="localSearch"
          type="search"
          maxlength="255"
          placeholder="Магазин, ИНН, организация…"
          class="mt-1 block w-full rounded-md border-slate-700 bg-slate-900 text-sm text-slate-100 shadow-sm placeholder:text-slate-500 focus:border-cyan-400 focus:ring-cyan-400"
          :disabled="isLoading"
          @keydown.enter.prevent="apply"
        >
      </label>

      <label class="block text-sm font-medium text-slate-200">
        <span>Статус</span>
        <select
          v-model="localStatus"
          class="mt-1 block w-full rounded-md border-slate-700 bg-slate-900 text-sm text-slate-100 shadow-sm focus:border-cyan-400 focus:ring-cyan-400"
          :disabled="isLoading"
        >
          <option
            v-for="option in statusOptions"
            :key="String(option.value ?? '')"
            :value="option.value ?? ''"
          >
            {{ option.label }}
          </option>
        </select>
      </label>

      <label class="block text-sm font-medium text-slate-200">
        <span>На странице</span>
        <select
          v-model.number="localPerPage"
          class="mt-1 block w-full rounded-md border-slate-700 bg-slate-900 text-sm text-slate-100 shadow-sm focus:border-cyan-400 focus:ring-cyan-400"
          :disabled="isLoading"
        >
          <option
            v-for="size in [5, 10, 15, 25, 50]"
            :key="size"
            :value="size"
          >
            {{ size }}
          </option>
        </select>
      </label>
    </div>

    <button
      type="button"
      class="inline-flex items-center justify-center rounded-lg border border-transparent bg-cyan-500 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-950 transition hover:bg-cyan-400 disabled:cursor-wait disabled:bg-slate-700 disabled:text-slate-400"
      :disabled="isLoading"
      @click="apply"
    >
      Применить
    </button>
  </div>
</template>
