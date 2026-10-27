<script setup>
import {
    formatHeadOrganizationDisplay,
    headOrganizationKinds as organizationKinds,
    isHeadOrganizationKindLabel,
    parseHeadOrganization,
} from '@/domain/headOrganizationKind';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    rowId: {
        type: [Number, String],
        required: true,
    },
    value: {
        type: String,
        default: '',
    },
    organizationType: {
        type: String,
        default: '',
    },
    saveAction: {
        type: Function,
        required: true,
    },
    poptipId: {
        type: String,
        default: null,
    },
    openPoptipId: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['open', 'close']);
const isOpen = ref(false);
const form = ref({
    kind: organizationKinds[0],
    name: '',
});
const initialForm = ref({ ...form.value });
const errors = ref({});
const isSaving = ref(false);
const serverError = ref('');

const resolvedPoptipId = computed(() => props.poptipId ?? `head-organization-poptip-${props.rowId}`);
const isControlled = computed(() => props.poptipId !== null);
const isPoptipOpen = computed(() => (
    isControlled.value
        ? props.openPoptipId === resolvedPoptipId.value
        : isOpen.value
));
const hasChanges = computed(() =>
    form.value.kind !== initialForm.value.kind
    || form.value.name.trim() !== initialForm.value.name.trim(),
);
const canSubmit = computed(() => hasChanges.value && !isSaving.value);
const displayValue = computed(() => formatHeadOrganizationDisplay(props.value, props.organizationType));

const resetForm = () => {
    const headOrganization = parseHeadOrganization(props.value, props.organizationType, organizationKinds[0]);

    form.value = { ...headOrganization };
    initialForm.value = { ...headOrganization };
    errors.value = {};
    serverError.value = '';
};

const openPoptip = () => {
    if (isSaving.value) {
        return;
    }

    resetForm();

    if (isControlled.value) {
        emit('open', resolvedPoptipId.value);
        return;
    }

    isOpen.value = true;
};

const closePoptip = () => {
    if (isSaving.value) {
        return;
    }

    if (isControlled.value) {
        emit('close');
    } else {
        isOpen.value = false;
    }

    errors.value = {};
    serverError.value = '';
};

const validate = () => {
    const validationErrors = {};
    const name = form.value.name.trim();

    if (!isHeadOrganizationKindLabel(form.value.kind)) {
        validationErrors.kind = 'Выберите вид организации';
    }

    if (name === '') {
        validationErrors.name = 'Заполните наименование';
    } else if (name.length > 256) {
        validationErrors.name = 'Наименование не должно превышать 256 символов';
    }

    errors.value = validationErrors;

    return Object.keys(validationErrors).length === 0;
};

const save = async () => {
    if (!hasChanges.value) {
        return;
    }

    if (!validate()) {
        return;
    }

    isSaving.value = true;
    serverError.value = '';

    try {
        await props.saveAction({
            rowId: props.rowId,
            head_organization: form.value.name.trim(),
            head_organization_type: form.value.kind,
        });
        initialForm.value = { ...form.value, name: form.value.name.trim() };

        if (isControlled.value) {
            emit('close');
        } else {
            isOpen.value = false;
        }
    } catch (error) {
        serverError.value = error?.message ?? 'Не удалось сохранить изменения';
    } finally {
        isSaving.value = false;
    }
};

watch(
    () => props.value,
    () => {
        if (isPoptipOpen.value) {
            resetForm();
        }
    },
);
</script>

<template>
  <div class="relative flex justify-center">
    <button
      type="button"
      class="max-w-full rounded-lg border border-cyan-400/30 bg-slate-950/50 px-3 py-2 text-left text-[#ff851b] transition hover:border-cyan-300 hover:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-cyan-400 focus:ring-offset-2 focus:ring-offset-slate-900 disabled:cursor-wait disabled:opacity-70"
      :aria-expanded="isPoptipOpen"
      :aria-controls="resolvedPoptipId"
      :disabled="isSaving"
      @click="openPoptip"
    >
      {{ displayValue }}
    </button>

    <div
      v-if="isPoptipOpen"
      :id="resolvedPoptipId"
      class="absolute left-1/2 top-full z-50 mt-3 w-80 -translate-x-1/2 rounded-2xl border border-cyan-400/30 bg-slate-950 p-4 text-left shadow-2xl shadow-black/60"
    >
      <span class="absolute -top-1.5 left-1/2 h-3 w-3 -translate-x-1/2 rotate-45 border-l border-t border-cyan-400/30 bg-slate-950" />
      <div class="text-sm font-semibold text-slate-100">
        Головная организация
      </div>
      <div class="mt-1 text-xs text-slate-400">
        Заполните обязательные поля для строки {{ rowId }}.
      </div>

      <form
        class="mt-4 space-y-4"
        :aria-busy="isSaving"
        @submit.prevent="save"
      >
        <label class="block text-sm font-medium text-slate-200">
          <span>Вид</span>
          <select
            v-model="form.kind"
            class="mt-1 block w-full rounded-md border-slate-700 bg-slate-900 text-sm text-slate-100 shadow-sm focus:border-cyan-400 focus:ring-cyan-400"
            :disabled="isSaving"
          >
            <option
              v-for="kind in organizationKinds"
              :key="kind"
              :value="kind"
            >
              {{ kind }}
            </option>
          </select>
          <span
            v-if="errors.kind"
            class="mt-1 block text-xs text-rose-300"
          >
            {{ errors.kind }}
          </span>
        </label>

        <label class="block text-sm font-medium text-slate-200">
          <span>Наименование</span>
          <input
            v-model.trim="form.name"
            type="text"
            maxlength="256"
            class="mt-1 block w-full rounded-md border-slate-700 bg-slate-900 text-sm text-slate-100 shadow-sm placeholder:text-slate-500 focus:border-cyan-400 focus:ring-cyan-400"
            placeholder="Введите наименование"
            :disabled="isSaving"
          >
          <span class="mt-1 block text-xs text-slate-500">
            {{ form.name.length }}/256
          </span>
          <span
            v-if="errors.name"
            class="mt-1 block text-xs text-rose-300"
          >
            {{ errors.name }}
          </span>
        </label>

        <p
          v-if="serverError"
          class="mt-1 block text-xs text-rose-300"
          aria-live="polite"
        >
          {{ serverError }}
        </p>

        <div class="flex justify-end gap-2">
          <button
            type="button"
            class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-slate-300 transition hover:text-white"
            :disabled="isSaving"
            @click="closePoptip"
          >
            Отмена
          </button>
          <button
            type="submit"
            class="inline-flex items-center gap-2 rounded-lg border border-transparent bg-cyan-500 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-slate-950 transition hover:bg-cyan-400 disabled:cursor-not-allowed disabled:bg-slate-700 disabled:text-slate-400"
            :disabled="!canSubmit"
            aria-live="polite"
          >
            <span
              v-if="isSaving"
              class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-slate-900 border-t-transparent"
              aria-hidden="true"
            />
            {{ isSaving ? 'Сохранение...' : 'Сохранить' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
