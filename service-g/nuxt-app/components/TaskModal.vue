<script setup lang="ts">
import type { Task, TaskPayload } from '~/types/task';

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit' | 'delete';
    task: Task | null;
    loading?: boolean;
}>();

const emit = defineEmits<{
    close: [];
    create: [payload: TaskPayload];
    save: [payload: TaskPayload];
    confirmDelete: [];
}>();

/** Сбрасывает форму создания при каждом открытии модального окна. */
const createFormKey = ref(0);

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen && props.mode === 'create') {
            createFormKey.value += 1;
        }
    },
);

const editInitial = computed<Partial<TaskPayload>>(() => {
    if (!props.task) {
        return {};
    }

    return {
        title: props.task.title,
        description: props.task.description,
        due_date: props.task.due_date,
        status: props.task.status,
    };
});

/** Закрывает модальное окно. */
function onClose(): void {
    if (!props.loading) {
        emit('close');
    }
}

/** Создаёт новую задачу из формы. */
function onCreate(payload: TaskPayload): void {
    emit('create', payload);
}

/** Сохраняет изменения задачи из формы редактирования. */
function onSave(payload: TaskPayload): void {
    emit('save', payload);
}

const modalTitle = computed(() => {
    if (props.mode === 'create') {
        return 'Новая задача';
    }

    if (props.mode === 'edit') {
        return 'Редактирование задачи';
    }

    return 'Удаление задачи';
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open && (task || mode === 'create')"
            class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
            role="dialog"
            aria-modal="true"
        >
            <button
                type="button"
                class="absolute inset-0 bg-slate-950/70 backdrop-blur-sm"
                aria-label="Закрыть"
                @click="onClose"
            />

            <div class="relative z-10 w-full max-w-lg rounded-xl border border-slate-800 bg-slate-900 p-5 shadow-2xl">
                <div class="mb-4 flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-white">
                            {{ modalTitle }}
                        </h2>
                        <p
                            v-if="mode === 'delete' && task"
                            class="mt-1 text-sm text-slate-400"
                        >
                            Вы уверены, что хотите удалить «{{ task.title }}»? Это действие нельзя отменить.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-md px-2 py-1 text-slate-400 transition hover:bg-slate-800 hover:text-white"
                        :disabled="loading"
                        @click="onClose"
                    >
                        ✕
                    </button>
                </div>

                <TaskForm
                    v-if="mode === 'create'"
                    :key="createFormKey"
                    :loading="loading"
                    @submit="onCreate"
                />

                <TaskForm
                    v-else-if="mode === 'edit'"
                    :initial="editInitial"
                    submit-label="Сохранить"
                    :loading="loading"
                    require-changes
                    @submit="onSave"
                />

                <div
                    v-else-if="mode === 'delete'"
                    class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"
                >
                    <button
                        type="button"
                        class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-300 transition hover:border-slate-500 hover:text-white"
                        :disabled="loading"
                        @click="onClose"
                    >
                        Отмена
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-500 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="loading"
                        @click="emit('confirmDelete')"
                    >
                        <AppSpinner
                            v-if="loading"
                            size="sm"
                            variant="on-primary"
                        />
                        <span>{{ loading ? 'Удаление…' : 'Удалить' }}</span>
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
