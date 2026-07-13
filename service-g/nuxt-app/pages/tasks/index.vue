<script setup lang="ts">
import type { Task, TaskPayload, TaskToolbarFilters } from '~/types/task';
import { parseTasksRouteQuery, tasksQueryToRouteQuery } from '~/utils/tasksQuery';

definePageMeta({
    middleware: 'auth',
});

const route = useRoute();
const router = useRouter();
const { user, logout, isLoading: authLoading } = useAuth();
const {
    tasks,
    pagination,
    loading,
    saving,
    listError,
    error,
    fetchTasks,
    createTask,
    updateTask,
    deleteTask,
} = useTasks();

const modalOpen = ref(false);
const modalMode = ref<'create' | 'edit' | 'delete'>('edit');
const selectedTask = ref<Task | null>(null);

const toolbarFilters = ref<TaskToolbarFilters>({
    status: '',
    search: '',
    sort: 'created_at',
    direction: 'desc',
});

/** Текущие query-параметры списка из URL. */
const listQuery = computed(() => parseTasksRouteQuery(route.query));

/** Синхронизирует фильтры toolbar с URL без debounce-поиска. */
function syncToolbarFromRoute(): void {
    toolbarFilters.value = {
        status: listQuery.value.status ?? '',
        search: listQuery.value.search ?? '',
        sort: listQuery.value.sort ?? 'created_at',
        direction: listQuery.value.direction ?? 'desc',
    };
}

/** Обновляет URL при изменении фильтров (сбрасывает page на 1). */
async function applyToolbarFilters(filters: TaskToolbarFilters): Promise<void> {
    toolbarFilters.value = filters;

    await router.replace({
        path: '/tasks',
        query: tasksQueryToRouteQuery({
            ...filters,
            page: 1,
        }),
    });
}

/** Загружает задачи по текущему URL. */
async function reloadTasks(): Promise<void> {
    await fetchTasks(listQuery.value);
}

watch(
    () => route.query,
    async () => {
        syncToolbarFromRoute();
        await reloadTasks();
    },
    { immediate: true },
);

/** Выходит из сессии и перенаправляет на страницу входа. */
async function onLogout(): Promise<void> {
    await logout();
    await navigateTo('/login');
}

/** Открывает модальное окно создания задачи. */
function openCreateModal(): void {
    selectedTask.value = null;
    modalMode.value = 'create';
    modalOpen.value = true;
}

/** Создаёт задачу и обновляет список. */
async function onCreateTask(payload: TaskPayload): Promise<void> {
    const created = await createTask(payload);

    if (!created) {
        return;
    }

    closeModal();
    await reloadTasks();
}

/** Открывает модальное окно редактирования. */
function onEditTask(task: Task): void {
    selectedTask.value = task;
    modalMode.value = 'edit';
    modalOpen.value = true;
}

/** Открывает модальное окно подтверждения удаления. */
function onDeleteTask(task: Task): void {
    selectedTask.value = task;
    modalMode.value = 'delete';
    modalOpen.value = true;
}

/** Закрывает модальное окно. */
function closeModal(): void {
    modalOpen.value = false;
    selectedTask.value = null;
}

/** Сохраняет изменения задачи из модального окна. */
async function onSaveTask(payload: TaskPayload): Promise<void> {
    if (!selectedTask.value) {
        return;
    }

    const updated = await updateTask(selectedTask.value.id, payload);

    if (!updated) {
        return;
    }

    closeModal();
    await reloadTasks();
}

/** Удаляет задачу после подтверждения. */
async function onConfirmDelete(): Promise<void> {
    if (!selectedTask.value) {
        return;
    }

    const deleted = await deleteTask(selectedTask.value.id);

    if (!deleted) {
        return;
    }

    closeModal();
    await reloadTasks();
}

/** Переходит на другую страницу пагинации. */
async function goToPage(page: number): Promise<void> {
    if (page < 1 || page > pagination.value.last_page || page === pagination.value.current_page) {
        return;
    }

    await router.replace({
        path: '/tasks',
        query: tasksQueryToRouteQuery({
            ...listQuery.value,
            page,
        }),
    });
}
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-4">
                <div>
                    <h1 class="text-2xl font-semibold text-white">
                        Мои задачи
                    </h1>
                    <p
                        v-if="user"
                        class="mt-1 text-sm text-slate-400"
                    >
                        {{ user.name }} · {{ user.email }}
                        <span
                            v-if="user.role === 'admin'"
                            class="ml-2 rounded-full border border-indigo-500/40 bg-indigo-500/10 px-2 py-0.5 text-xs text-indigo-200"
                        >
                            admin
                        </span>
                    </p>
                </div>

                <button
                    type="button"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-500"
                    @click="openCreateModal"
                >
                    Новая задача
                </button>
            </div>

            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-300 transition hover:border-slate-500 hover:text-white disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="authLoading"
                @click="onLogout"
            >
                <AppSpinner
                    v-if="authLoading"
                    size="sm"
                />
                <span>{{ authLoading ? 'Выход…' : 'Выйти' }}</span>
            </button>
        </div>

        <TaskToolbar
            :model-value="toolbarFilters"
            :disabled="loading"
            :loading="loading"
            @update:model-value="applyToolbarFilters"
        />

        <div
            v-if="error"
            class="rounded-lg border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-200"
            role="alert"
        >
            {{ error }}
        </div>

        <section class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-lg font-medium text-slate-200">
                    Список задач
                </h2>
                <p class="text-sm text-slate-500">
                    Всего: {{ pagination.total }}
                </p>
            </div>

            <TaskList
                :tasks="tasks"
                :loading="loading"
                :list-error="listError"
                :user="user"
                @edit="onEditTask"
                @delete="onDeleteTask"
                @retry="reloadTasks"
            />

            <nav
                v-if="pagination.last_page > 1"
                class="flex flex-wrap items-center justify-center gap-2 pt-2"
                aria-label="Пагинация"
            >
                <AppSpinner
                    v-if="loading"
                    size="sm"
                    label="Загрузка…"
                />

                <button
                    type="button"
                    class="rounded-lg border border-slate-700 px-3 py-1.5 text-sm text-slate-300 transition hover:border-slate-500 hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="loading || pagination.current_page <= 1"
                    @click="goToPage(pagination.current_page - 1)"
                >
                    Назад
                </button>

                <span class="px-2 text-sm text-slate-400">
                    Страница {{ pagination.current_page }} из {{ pagination.last_page }}
                </span>

                <button
                    type="button"
                    class="rounded-lg border border-slate-700 px-3 py-1.5 text-sm text-slate-300 transition hover:border-slate-500 hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="loading || pagination.current_page >= pagination.last_page"
                    @click="goToPage(pagination.current_page + 1)"
                >
                    Вперёд
                </button>
            </nav>
        </section>

        <TaskModal
            :open="modalOpen"
            :mode="modalMode"
            :task="selectedTask"
            :loading="saving"
            @close="closeModal"
            @create="onCreateTask"
            @save="onSaveTask"
            @confirm-delete="onConfirmDelete"
        />
    </div>
</template>
