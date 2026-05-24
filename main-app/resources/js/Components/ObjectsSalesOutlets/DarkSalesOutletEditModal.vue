<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    row: {
        type: Object,
        default: null,
    },
    isSaving: {
        type: Boolean,
        default: false,
    },
    serverError: {
        type: String,
        default: '',
    },
    fieldErrors: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['close', 'save']);

const organizationKinds = ['ООО', 'ИП', 'АО', 'СПК'];
const organizationKindLabels = {
    ooo: 'ООО',
    ip: 'ИП',
    ao: 'АО',
    spk: 'СПК',
};
const statuses = [
    { value: 'approved', label: 'Одобрено' },
    { value: 'review', label: 'На проверке' },
    { value: 'blocked', label: 'Есть изменения' },
];
const emptyForm = () => ({
    shop: '',
    manager: '',
    curator: '',
    name: '',
    inn: '',
    head_organization: '',
    head_organization_type: organizationKinds[0],
    organization_name: '',
    status: statuses[0].value,
});
const form = ref(emptyForm());
const clientErrors = ref({});

const title = computed(() => (props.row ? `Редактировать объект продаж ${props.row.id}` : 'Редактировать объект продаж'));
const hasFieldError = (field) => Boolean(clientErrors.value[field] || props.fieldErrors[field]?.length);
const fieldError = (field) => clientErrors.value[field] || props.fieldErrors[field]?.[0] || '';
const canSubmit = computed(() => ! props.isSaving && props.row !== null);

const normalizeOrganizationType = (value) => {
    const normalizedValue = String(value ?? '').trim();

    if (organizationKinds.includes(normalizedValue)) {
        return normalizedValue;
    }

    return organizationKindLabels[normalizedValue.toLowerCase()] ?? organizationKinds[0];
};

const parseHeadOrganization = (value) => {
    const trimmedValue = String(value ?? '').trim();
    const matchedKind = organizationKinds.find((kind) => trimmedValue.startsWith(`${kind} `));

    return matchedKind
        ? trimmedValue.slice(matchedKind.length).trim()
        : trimmedValue;
};

const fillForm = () => {
    if (! props.row) {
        form.value = emptyForm();
        return;
    }

    form.value = {
        shop: String(props.row.shop ?? ''),
        manager: String(props.row.manager ?? ''),
        curator: String(props.row.curator ?? ''),
        name: String(props.row.name ?? ''),
        inn: String(props.row.inn ?? ''),
        head_organization: parseHeadOrganization(props.row.head_organization),
        head_organization_type: normalizeOrganizationType(
            props.row.head_organization_type || props.row.head_organization_type_label,
        ),
        organization_name: String(props.row.organization_name ?? ''),
        status: String(props.row.status ?? statuses[0].value),
    };
    clientErrors.value = {};
};

const validate = () => {
    const errors = {};
    const requiredFields = {
        shop: 'Заполните магазин',
        manager: 'Заполните менеджера',
        curator: 'Заполните куратора',
        name: 'Заполните название ТТ',
        inn: 'Заполните ИНН',
        head_organization: 'Заполните головную организацию',
        organization_name: 'Заполните название организации',
    };

    Object.entries(requiredFields).forEach(([field, message]) => {
        if (String(form.value[field] ?? '').trim() === '') {
            errors[field] = message;
        }
    });

    if (form.value.inn && ! /^\d{10}(\d{2})?$/.test(form.value.inn)) {
        errors.inn = 'ИНН должен содержать 10 или 12 цифр';
    }

    if (! organizationKinds.includes(form.value.head_organization_type)) {
        errors.head_organization_type = 'Выберите вид организации';
    }

    if (! statuses.some((status) => status.value === form.value.status)) {
        errors.status = 'Выберите статус';
    }

    clientErrors.value = errors;

    return Object.keys(errors).length === 0;
};

const close = () => {
    if (props.isSaving) {
        return;
    }

    emit('close');
};

const submit = () => {
    if (! validate()) {
        return;
    }

    emit('save', {
        shop: form.value.shop.trim(),
        manager: form.value.manager.trim(),
        curator: form.value.curator.trim(),
        name: form.value.name.trim(),
        inn: form.value.inn.trim(),
        head_organization: form.value.head_organization.trim(),
        head_organization_type: form.value.head_organization_type,
        organization_name: form.value.organization_name.trim(),
        status: form.value.status,
    });
};

watch(
    () => [props.show, props.row],
    () => {
        if (props.show) {
            fillForm();
        }
    },
    { immediate: true },
);
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 px-4 py-6"
        role="dialog"
        aria-modal="true"
        :aria-label="title"
    >
        <div class="max-h-full w-full max-w-3xl overflow-y-auto rounded-2xl border border-cyan-400/30 bg-slate-950 text-slate-100 shadow-2xl shadow-black/60">
            <div class="flex items-start justify-between gap-4 border-b border-slate-800 px-6 py-5">
                <div>
                    <h3 class="text-lg font-semibold text-white">
                        {{ title }}
                    </h3>
                    <p class="mt-1 text-sm text-slate-400">
                        Измените основные поля объекта продаж и сохраните результат в service-a.
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-1.5 text-sm font-semibold text-slate-300 transition hover:text-white disabled:cursor-wait disabled:opacity-60"
                    :disabled="isSaving"
                    @click="close"
                >
                    Закрыть
                </button>
            </div>

            <form
                class="space-y-5 px-6 py-5"
                :aria-busy="isSaving"
                @submit.prevent="submit"
            >
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block text-sm font-medium text-slate-200">
                        <span>Магазин</span>
                        <input
                            v-model="form.shop"
                            type="text"
                            maxlength="255"
                            class="mt-1 block w-full rounded-md border-slate-700 bg-slate-900 text-sm text-slate-100 shadow-sm placeholder:text-slate-500 focus:border-cyan-400 focus:ring-cyan-400"
                            :class="{ 'border-rose-400': hasFieldError('shop') }"
                            :disabled="isSaving"
                        />
                        <span
                            v-if="hasFieldError('shop')"
                            class="mt-1 block text-xs text-rose-300"
                        >
                            {{ fieldError('shop') }}
                        </span>
                    </label>

                    <label class="block text-sm font-medium text-slate-200">
                        <span>Название ТТ</span>
                        <input
                            v-model="form.name"
                            type="text"
                            maxlength="255"
                            class="mt-1 block w-full rounded-md border-slate-700 bg-slate-900 text-sm text-slate-100 shadow-sm placeholder:text-slate-500 focus:border-cyan-400 focus:ring-cyan-400"
                            :class="{ 'border-rose-400': hasFieldError('name') }"
                            :disabled="isSaving"
                        />
                        <span
                            v-if="hasFieldError('name')"
                            class="mt-1 block text-xs text-rose-300"
                        >
                            {{ fieldError('name') }}
                        </span>
                    </label>

                    <label class="block text-sm font-medium text-slate-200">
                        <span>Менеджер</span>
                        <input
                            v-model="form.manager"
                            type="text"
                            maxlength="255"
                            class="mt-1 block w-full rounded-md border-slate-700 bg-slate-900 text-sm text-slate-100 shadow-sm placeholder:text-slate-500 focus:border-cyan-400 focus:ring-cyan-400"
                            :class="{ 'border-rose-400': hasFieldError('manager') }"
                            :disabled="isSaving"
                        />
                        <span
                            v-if="hasFieldError('manager')"
                            class="mt-1 block text-xs text-rose-300"
                        >
                            {{ fieldError('manager') }}
                        </span>
                    </label>

                    <label class="block text-sm font-medium text-slate-200">
                        <span>Куратор ТТ</span>
                        <input
                            v-model="form.curator"
                            type="text"
                            maxlength="255"
                            class="mt-1 block w-full rounded-md border-slate-700 bg-slate-900 text-sm text-slate-100 shadow-sm placeholder:text-slate-500 focus:border-cyan-400 focus:ring-cyan-400"
                            :class="{ 'border-rose-400': hasFieldError('curator') }"
                            :disabled="isSaving"
                        />
                        <span
                            v-if="hasFieldError('curator')"
                            class="mt-1 block text-xs text-rose-300"
                        >
                            {{ fieldError('curator') }}
                        </span>
                    </label>

                    <label class="block text-sm font-medium text-slate-200">
                        <span>ИНН головной</span>
                        <input
                            v-model="form.inn"
                            type="text"
                            inputmode="numeric"
                            maxlength="12"
                            class="mt-1 block w-full rounded-md border-slate-700 bg-slate-900 text-sm text-slate-100 shadow-sm placeholder:text-slate-500 focus:border-cyan-400 focus:ring-cyan-400"
                            :class="{ 'border-rose-400': hasFieldError('inn') }"
                            :disabled="isSaving"
                        />
                        <span
                            v-if="hasFieldError('inn')"
                            class="mt-1 block text-xs text-rose-300"
                        >
                            {{ fieldError('inn') }}
                        </span>
                    </label>

                    <label class="block text-sm font-medium text-slate-200">
                        <span>Статус</span>
                        <select
                            v-model="form.status"
                            class="mt-1 block w-full rounded-md border-slate-700 bg-slate-900 text-sm text-slate-100 shadow-sm focus:border-cyan-400 focus:ring-cyan-400"
                            :class="{ 'border-rose-400': hasFieldError('status') }"
                            :disabled="isSaving"
                        >
                            <option
                                v-for="status in statuses"
                                :key="status.value"
                                :value="status.value"
                            >
                                {{ status.label }}
                            </option>
                        </select>
                        <span
                            v-if="hasFieldError('status')"
                            class="mt-1 block text-xs text-rose-300"
                        >
                            {{ fieldError('status') }}
                        </span>
                    </label>

                    <label class="block text-sm font-medium text-slate-200 md:col-span-2">
                        <span>Головная организация</span>
                        <input
                            v-model="form.head_organization"
                            type="text"
                            maxlength="255"
                            class="mt-1 block w-full rounded-md border-slate-700 bg-slate-900 text-sm text-slate-100 shadow-sm placeholder:text-slate-500 focus:border-cyan-400 focus:ring-cyan-400"
                            :class="{ 'border-rose-400': hasFieldError('head_organization') }"
                            :disabled="isSaving"
                        />
                        <span
                            v-if="hasFieldError('head_organization')"
                            class="mt-1 block text-xs text-rose-300"
                        >
                            {{ fieldError('head_organization') }}
                        </span>
                    </label>

                    <label class="block text-sm font-medium text-slate-200">
                        <span>Вид</span>
                        <select
                            v-model="form.head_organization_type"
                            class="mt-1 block w-full rounded-md border-slate-700 bg-slate-900 text-sm text-slate-100 shadow-sm focus:border-cyan-400 focus:ring-cyan-400"
                            :class="{ 'border-rose-400': hasFieldError('head_organization_type') }"
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
                            v-if="hasFieldError('head_organization_type')"
                            class="mt-1 block text-xs text-rose-300"
                        >
                            {{ fieldError('head_organization_type') }}
                        </span>
                    </label>

                    <label class="block text-sm font-medium text-slate-200">
                        <span>Название организации</span>
                        <input
                            v-model="form.organization_name"
                            type="text"
                            maxlength="255"
                            class="mt-1 block w-full rounded-md border-slate-700 bg-slate-900 text-sm text-slate-100 shadow-sm placeholder:text-slate-500 focus:border-cyan-400 focus:ring-cyan-400"
                            :class="{ 'border-rose-400': hasFieldError('organization_name') }"
                            :disabled="isSaving"
                        />
                        <span
                            v-if="hasFieldError('organization_name')"
                            class="mt-1 block text-xs text-rose-300"
                        >
                            {{ fieldError('organization_name') }}
                        </span>
                    </label>
                </div>

                <p
                    v-if="serverError"
                    class="rounded-lg border border-rose-400/30 bg-rose-950/50 px-4 py-3 text-sm text-rose-200"
                    aria-live="polite"
                >
                    {{ serverError }}
                </p>

                <div class="flex justify-end gap-3 border-t border-slate-800 pt-5">
                    <button
                        type="button"
                        class="rounded-lg border border-slate-700 bg-slate-900 px-4 py-2 text-sm font-semibold text-slate-300 transition hover:text-white disabled:cursor-wait disabled:opacity-60"
                        :disabled="isSaving"
                        @click="close"
                    >
                        Отмена
                    </button>
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 rounded-lg border border-transparent bg-cyan-500 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-cyan-400 disabled:cursor-not-allowed disabled:bg-slate-700 disabled:text-slate-400"
                        :disabled="!canSubmit"
                    >
                        <span
                            v-if="isSaving"
                            class="h-4 w-4 animate-spin rounded-full border-2 border-slate-900 border-t-transparent"
                            aria-hidden="true"
                        ></span>
                        {{ isSaving ? 'Сохранение...' : 'Сохранить' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
