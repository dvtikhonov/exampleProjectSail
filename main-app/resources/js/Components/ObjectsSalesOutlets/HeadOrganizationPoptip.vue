<script setup>
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
    variant: {
        type: String,
        default: 'light',
        validator: (value) => ['light', 'dark'].includes(value),
    },
});

const emit = defineEmits(['save']);

const organizationKinds = ['ООО', 'ИП', 'СПК'];
const isOpen = ref(false);
const form = ref({
    kind: organizationKinds[0],
    name: '',
});
const initialForm = ref({ ...form.value });
const errors = ref({});

const poptipId = computed(() => `head-organization-poptip-${props.rowId}`);
const hasChanges = computed(() =>
    form.value.kind !== initialForm.value.kind
    || form.value.name.trim() !== initialForm.value.name.trim(),
);
const styles = computed(() => {
    if (props.variant === 'dark') {
        return {
            button: 'max-w-full rounded-lg border border-cyan-400/30 bg-slate-950/50 px-3 py-2 text-left text-[#ff851b] transition hover:border-cyan-300 hover:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-cyan-400 focus:ring-offset-2 focus:ring-offset-slate-900',
            panel: 'absolute left-1/2 top-full z-50 mt-3 w-80 -translate-x-1/2 rounded-2xl border border-cyan-400/30 bg-slate-950 p-4 text-left shadow-2xl shadow-black/60',
            arrow: 'absolute -top-1.5 left-1/2 h-3 w-3 -translate-x-1/2 rotate-45 border-l border-t border-cyan-400/30 bg-slate-950',
            title: 'text-sm font-semibold text-slate-100',
            description: 'mt-1 text-xs text-slate-400',
            label: 'block text-sm font-medium text-slate-200',
            select: 'mt-1 block w-full rounded-md border-slate-700 bg-slate-900 text-sm text-slate-100 shadow-sm focus:border-cyan-400 focus:ring-cyan-400',
            input: 'mt-1 block w-full rounded-md border-slate-700 bg-slate-900 text-sm text-slate-100 shadow-sm placeholder:text-slate-500 focus:border-cyan-400 focus:ring-cyan-400',
            counter: 'mt-1 block text-xs text-slate-500',
            error: 'mt-1 block text-xs text-rose-300',
            cancelButton: 'rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-slate-300 transition hover:text-white',
            saveButton: 'rounded-lg border border-transparent bg-cyan-500 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-slate-950 transition hover:bg-cyan-400 disabled:cursor-not-allowed disabled:bg-slate-700 disabled:text-slate-400',
        };
    }

    return {
        button: 'max-w-full rounded-md border border-emerald-200 bg-white/90 px-3 py-2 text-left text-gray-900 shadow-sm transition hover:border-emerald-400 hover:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2',
        panel: 'absolute left-1/2 top-full z-50 mt-3 w-80 -translate-x-1/2 rounded-xl border border-emerald-200 bg-white p-4 text-left text-gray-900 shadow-xl ring-1 ring-black/5',
        arrow: 'absolute -top-1.5 left-1/2 h-3 w-3 -translate-x-1/2 rotate-45 border-l border-t border-emerald-200 bg-white',
        title: 'text-sm font-semibold text-gray-900',
        description: 'mt-1 text-xs text-gray-500',
        label: 'block text-sm font-medium text-gray-700',
        select: 'mt-1 block w-full rounded-md border-gray-300 text-sm text-gray-900 shadow-sm focus:border-emerald-500 focus:ring-emerald-500',
        input: 'mt-1 block w-full rounded-md border-gray-300 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-emerald-500 focus:ring-emerald-500',
        counter: 'mt-1 block text-xs text-gray-500',
        error: 'mt-1 block text-xs text-rose-600',
        cancelButton: 'rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 transition hover:bg-gray-50',
        saveButton: 'rounded-md border border-transparent bg-emerald-500 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-emerald-600 disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-400',
    };
});

const parseHeadOrganization = (value) => {
    const trimmedValue = String(value ?? '').trim();
    const matchedKind = organizationKinds.find((kind) => trimmedValue.startsWith(`${kind} `));

    if (! matchedKind) {
        return {
            kind: organizationKinds[0],
            name: trimmedValue,
        };
    }

    return {
        kind: matchedKind,
        name: trimmedValue.slice(matchedKind.length).trim(),
    };
};

const resetForm = () => {
    const headOrganization = parseHeadOrganization(props.value);

    form.value = { ...headOrganization };
    initialForm.value = { ...headOrganization };
    errors.value = {};
};

const openPoptip = () => {
    resetForm();
    isOpen.value = true;
};

const closePoptip = () => {
    isOpen.value = false;
    errors.value = {};
};

const validate = () => {
    const validationErrors = {};
    const name = form.value.name.trim();

    if (! organizationKinds.includes(form.value.kind)) {
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

const save = () => {
    if (! hasChanges.value) {
        return;
    }

    if (! validate()) {
        return;
    }

    emit('save', {
        rowId: props.rowId,
        value: `${form.value.kind} ${form.value.name.trim()}`,
    });
    closePoptip();
};

watch(
    () => props.value,
    () => {
        if (isOpen.value) {
            resetForm();
        }
    },
);
</script>

<template>
    <div class="relative flex justify-center">
        <button
            type="button"
            :class="styles.button"
            :aria-expanded="isOpen"
            :aria-controls="poptipId"
            @click="openPoptip"
        >
            {{ value }}
        </button>

        <div
            v-if="isOpen"
            :id="poptipId"
            :class="styles.panel"
        >
            <span :class="styles.arrow"></span>
            <div :class="styles.title">
                Головная организация
            </div>
            <div :class="styles.description">
                Заполните обязательные поля для строки {{ rowId }}.
            </div>

            <form
                class="mt-4 space-y-4"
                @submit.prevent="save"
            >
                <label :class="styles.label">
                    <span>Вид</span>
                    <select
                        v-model="form.kind"
                        :class="styles.select"
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
                        :class="styles.error"
                    >
                        {{ errors.kind }}
                    </span>
                </label>

                <label :class="styles.label">
                    <span>Наименование</span>
                    <input
                        v-model.trim="form.name"
                        type="text"
                        maxlength="256"
                        :class="styles.input"
                        placeholder="Введите наименование"
                    />
                    <span :class="styles.counter">
                        {{ form.name.length }}/256
                    </span>
                    <span
                        v-if="errors.name"
                        :class="styles.error"
                    >
                        {{ errors.name }}
                    </span>
                </label>

                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        :class="styles.cancelButton"
                        @click="closePoptip"
                    >
                        Отмена
                    </button>
                    <button
                        type="submit"
                        :class="styles.saveButton"
                        :disabled="!hasChanges"
                    >
                        Сохранить
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
