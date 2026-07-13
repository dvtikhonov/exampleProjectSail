<script setup lang="ts">
import type { TaskPayload, TaskStatus } from '~/types/task';
import { TASK_STATUS_OPTIONS } from '~/types/task';

const props = withDefaults(defineProps<{
    initial?: Partial<TaskPayload>;
    submitLabel?: string;
    loading?: boolean;
    /** В режиме редактирования: кнопка отправки недоступна, пока хотя бы одно поле не изменено. */
    requireChanges?: boolean;
}>(), {
    initial: () => ({}),
    submitLabel: 'Создать задачу',
    loading: false,
    requireChanges: false,
});

const emit = defineEmits<{
    submit: [payload: TaskPayload];
}>();

const form = reactive({
    title: props.initial.title ?? '',
    description: props.initial.description ?? '',
    due_date: props.initial.due_date ?? '',
    status: (props.initial.status ?? 'pending') as TaskStatus,
});

const fieldErrors = reactive({
    title: '',
    due_date: '',
});

watch(
    () => props.initial,
    (value) => {
        form.title = value?.title ?? '';
        form.description = value?.description ?? '';
        form.due_date = value?.due_date ?? '';
        form.status = (value?.status ?? 'pending') as TaskStatus;
        fieldErrors.title = '';
        fieldErrors.due_date = '';
    },
    { deep: true },
);

/** Нормализует значения полей для сравнения с исходными данными задачи. */
function normalizePayload(value: Partial<TaskPayload>): TaskPayload {
    return {
        title: (value.title ?? '').trim(),
        description: (value.description ?? '').trim() || null,
        due_date: value.due_date ?? '',
        status: (value.status ?? 'pending') as TaskStatus,
    };
}

const hasChanges = computed(() => {
    if (!props.requireChanges) {
        return true;
    }

    const baseline = normalizePayload(props.initial);
    const current = normalizePayload(form);

    return (
        baseline.title !== current.title
        || baseline.description !== current.description
        || baseline.due_date !== current.due_date
        || baseline.status !== current.status
    );
});

const isSubmitDisabled = computed(() => props.loading || (props.requireChanges && !hasChanges.value));

/** Клиентская валидация формы задачи. */
function validate(): boolean {
    fieldErrors.title = '';
    fieldErrors.due_date = '';

    const title = form.title.trim();

    if (title.length < 3) {
        fieldErrors.title = 'Название должно содержать минимум 3 символа.';
    }

    if (!form.due_date.trim()) {
        fieldErrors.due_date = 'Укажите срок выполнения.';
    } else {
        const dueDate = new Date(`${form.due_date}T00:00:00`);
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        if (Number.isNaN(dueDate.getTime())) {
            fieldErrors.due_date = 'Укажите корректную дату.';
        } else if (dueDate < today) {
            fieldErrors.due_date = 'Срок не может быть в прошлом.';
        }
    }

    return !fieldErrors.title && !fieldErrors.due_date;
}

/** Отправляет форму после валидации. */
function onSubmit(): void {
    if (!validate()) {
        return;
    }

    emit('submit', {
        title: form.title.trim(),
        description: form.description.trim() || null,
        due_date: form.due_date,
        status: form.status,
    });
}

/** Сбрасывает поля формы. */
function reset(): void {
    form.title = '';
    form.description = '';
    form.due_date = '';
    form.status = 'pending';
    fieldErrors.title = '';
    fieldErrors.due_date = '';
}

defineExpose({ reset });
</script>

<template>
    <form
        class="space-y-4 rounded-xl border border-slate-800 bg-slate-900/60 p-4"
        @submit.prevent="onSubmit"
    >
        <div class="space-y-1">
            <label
                for="task-title"
                class="block text-sm font-medium text-slate-300"
            >
                Название *
            </label>
            <input
                id="task-title"
                v-model="form.title"
                type="text"
                required
                minlength="3"
                maxlength="255"
                class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white outline-none ring-indigo-500 focus:ring-2"
                placeholder="Например: Подготовить отчёт"
            >
            <p
                v-if="fieldErrors.title"
                class="text-sm text-rose-400"
            >
                {{ fieldErrors.title }}
            </p>
        </div>

        <div class="space-y-1">
            <label
                for="task-description"
                class="block text-sm font-medium text-slate-300"
            >
                Описание
            </label>
            <textarea
                id="task-description"
                v-model="form.description"
                rows="3"
                class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white outline-none ring-indigo-500 focus:ring-2"
                placeholder="Дополнительные детали…"
            />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-1">
                <label
                    for="task-form-due-date"
                    class="block text-sm font-medium text-slate-300"
                >
                    Срок *
                </label>
                <input
                    id="task-form-due-date"
                    v-model="form.due_date"
                    type="date"
                    required
                    class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white outline-none ring-indigo-500 focus:ring-2"
                >
                <p
                    v-if="fieldErrors.due_date"
                    class="text-sm text-rose-400"
                >
                    {{ fieldErrors.due_date }}
                </p>
            </div>

            <div class="space-y-1">
                <label
                    for="task-form-status"
                    class="block text-sm font-medium text-slate-300"
                >
                    Статус *
                </label>
                <select
                    id="task-form-status"
                    v-model="form.status"
                    class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white outline-none ring-indigo-500 focus:ring-2"
                >
                    <option
                        v-for="option in TASK_STATUS_OPTIONS"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </div>
        </div>

        <button
            type="submit"
            :disabled="isSubmitDisabled"
            class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 font-medium text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
        >
            <AppSpinner
                v-if="loading"
                size="sm"
                variant="on-primary"
            />
            <span>{{ loading ? 'Сохранение…' : submitLabel }}</span>
        </button>
    </form>
</template>
