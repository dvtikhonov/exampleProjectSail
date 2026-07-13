<script setup lang="ts">
import type { AuthUser } from '~/types/auth';
import type { Task, TaskStatus } from '~/types/task';
import { TASK_STATUS_OPTIONS } from '~/types/task';

const props = defineProps<{
    tasks: Task[];
    loading?: boolean;
    listError?: string | null;
    user: AuthUser | null;
}>();

const emit = defineEmits<{
    edit: [task: Task];
    delete: [task: Task];
    retry: [];
}>();

const statusLabels = Object.fromEntries(
    TASK_STATUS_OPTIONS.map((option) => [option.value, option.label]),
) as Record<TaskStatus, string>;

const statusClasses: Record<TaskStatus, string> = {
    pending: 'border-amber-500/40 bg-amber-500/10 text-amber-200',
    in_progress: 'border-sky-500/40 bg-sky-500/10 text-sky-200',
    completed: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-200',
};

/** Является ли текущий пользователь владельцем задачи. */
function isTaskOwner(task: Task): boolean {
    return props.user !== null && task.user_id === props.user.id;
}

/** Показывать ли блок действий над задачей (свои задачи или любые для admin). */
function showsTaskActions(task: Task): boolean {
    if (!props.user) {
        return false;
    }

    return isTaskOwner(task) || props.user.role === 'admin';
}

/** Может ли текущий пользователь редактировать задачу (только владелец). */
function canEditTask(task: Task): boolean {
    return isTaskOwner(task);
}

/** Может ли текущий пользователь удалить задачу (только владелец). */
function canDeleteTask(task: Task): boolean {
    return isTaskOwner(task);
}

/** Форматирует дату срока для отображения. */
function formatDueDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${value}T00:00:00`));
}
</script>

<template>
    <div class="space-y-3">
        <div
            v-if="listError && tasks.length > 0"
            class="rounded-lg border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-200"
            role="alert"
        >
            <p>{{ listError }}</p>
            <button
                type="button"
                class="mt-2 rounded-lg border border-rose-400/50 px-3 py-1.5 text-sm text-rose-100 transition hover:border-rose-300 hover:bg-rose-500/20 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="loading"
                @click="emit('retry')"
            >
                Повторить
            </button>
        </div>

        <div
            v-if="loading"
            class="rounded-xl border border-slate-800 bg-slate-900/40"
        >
            <AppSpinner
                centered
                size="lg"
                label="Загрузка задач…"
            />
        </div>

        <div
            v-else-if="listError && tasks.length === 0"
            class="rounded-xl border border-rose-500/40 bg-rose-500/10 p-6 text-center"
            role="alert"
        >
            <p class="text-lg font-medium text-rose-100">
                Не удалось загрузить задачи
            </p>
            <p class="mt-2 text-sm text-rose-200/90">
                {{ listError }}
            </p>
            <button
                type="button"
                class="mt-4 rounded-lg border border-rose-400/50 px-4 py-2 text-sm text-rose-100 transition hover:border-rose-300 hover:bg-rose-500/20 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="loading"
                @click="emit('retry')"
            >
                Повторить
            </button>
        </div>

        <div
            v-else-if="tasks.length === 0"
            class="rounded-xl border border-dashed border-slate-700 bg-slate-900/40 p-8 text-center"
        >
            <p class="text-lg font-medium text-slate-200">
                Задач пока нет
            </p>
            <p class="mt-2 text-sm text-slate-400">
                Создайте первую задачу или измените фильтры поиска.
            </p>
        </div>

        <div
            v-else
            class="space-y-3"
        >
            <article
                v-for="task in tasks"
                :key="task.id"
                class="rounded-xl border border-slate-800 bg-slate-900/60 p-4 transition hover:border-slate-700"
            >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 flex-1 space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-base font-semibold text-white">
                                {{ task.title }}
                            </h3>
                            <span
                                class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-medium"
                                :class="statusClasses[task.status]"
                            >
                                {{ statusLabels[task.status] }}
                            </span>
                        </div>

                        <p
                            v-if="task.description"
                            class="text-sm text-slate-400"
                        >
                            {{ task.description }}
                        </p>

                        <dl class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                            <div>
                                <dt class="inline">
                                    Срок:
                                </dt>
                                <dd class="inline text-slate-300">
                                    {{ formatDueDate(task.due_date) }}
                                </dd>
                            </div>
                            <div v-if="task.owner_name">
                                <dt class="inline">
                                    Автор:
                                </dt>
                                <dd class="inline text-slate-300">
                                    {{ task.owner_name }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div
                        v-if="showsTaskActions(task)"
                        class="flex shrink-0 gap-2"
                    >
                        <button
                            type="button"
                            class="rounded-lg border border-slate-700 px-3 py-1.5 text-sm text-slate-300 transition hover:border-indigo-500 hover:text-white disabled:cursor-not-allowed disabled:border-slate-800 disabled:text-slate-600 disabled:hover:border-slate-800 disabled:hover:text-slate-600"
                            :disabled="!canEditTask(task)"
                            @click="emit('edit', task)"
                        >
                            Изменить
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-rose-500/40 px-3 py-1.5 text-sm text-rose-300 transition hover:border-rose-400 hover:bg-rose-500/10 disabled:cursor-not-allowed disabled:border-slate-800 disabled:text-slate-600 disabled:hover:border-slate-800 disabled:hover:bg-transparent"
                            :disabled="!canDeleteTask(task)"
                            @click="emit('delete', task)"
                        >
                            Удалить
                        </button>
                    </div>
                </div>
            </article>
        </div>
    </div>
</template>
