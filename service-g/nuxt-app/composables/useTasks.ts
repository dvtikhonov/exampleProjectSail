import type {
    ListTasksQuery,
    PaginatedTasksResponse,
    Task,
    TaskPayload,
    TaskPaginationMeta,
    TaskResponse,
} from '~/types/task';

const defaultPagination = (): TaskPaginationMeta => ({
    current_page: 1,
    last_page: 1,
    per_page: 5,
    total: 0,
});

/**
 * Собирает query-string для GET /api/tasks.
 */
export function buildTasksQueryString(query: ListTasksQuery = {}): string {
    const params = new URLSearchParams();

    if (query.status) {
        params.set('status', query.status);
    }

    if (query.search?.trim()) {
        params.set('search', query.search.trim());
    }

    if (query.sort) {
        params.set('sort', query.sort);
    }

    if (query.direction) {
        params.set('direction', query.direction);
    }

    if (query.page && query.page > 1) {
        params.set('page', String(query.page));
    }

    if (query.per_page && query.per_page !== 5) {
        params.set('per_page', String(query.per_page));
    }

    const queryString = params.toString();

    return queryString ? `?${queryString}` : '';
}

/**
 * Composable CRUD задач через /api/tasks.
 */
export function useTasks() {
    const { apiFetch, extractErrorMessage } = useApi();

    const tasks = useState<Task[]>('tasks-list', () => []);
    const pagination = useState<TaskPaginationMeta>('tasks-pagination', defaultPagination);
    const loading = useState('tasks-loading', () => false);
    const saving = useState('tasks-saving', () => false);
    const listError = useState<string | null>('tasks-list-error', () => null);
    const error = useState<string | null>('tasks-error', () => null);

    /** Загружает список задач с учётом query-параметров. */
    async function fetchTasks(query: ListTasksQuery = {}): Promise<void> {
        loading.value = true;
        listError.value = null;

        try {
            const response = await apiFetch<PaginatedTasksResponse>(
                `/tasks${buildTasksQueryString(query)}`,
            );
            tasks.value = response.data;
            pagination.value = response.meta;
        } catch (err: unknown) {
            listError.value = extractErrorMessage(err, 'Не удалось загрузить задачи.');
        } finally {
            loading.value = false;
        }
    }

    /** Создаёт новую задачу. */
    async function createTask(payload: TaskPayload): Promise<Task | null> {
        saving.value = true;
        error.value = null;

        try {
            const response = await apiFetch<TaskResponse>('/tasks', {
                method: 'POST',
                body: payload,
            });

            return response.data;
        } catch (err: unknown) {
            error.value = extractErrorMessage(err, 'Не удалось создать задачу.');

            return null;
        } finally {
            saving.value = false;
        }
    }

    /** Обновляет существующую задачу. */
    async function updateTask(id: number, payload: Partial<TaskPayload>): Promise<Task | null> {
        saving.value = true;
        error.value = null;

        try {
            const response = await apiFetch<TaskResponse>(`/tasks/${id}`, {
                method: 'PATCH',
                body: payload,
            });

            return response.data;
        } catch (err: unknown) {
            error.value = extractErrorMessage(err, 'Не удалось обновить задачу.');

            return null;
        } finally {
            saving.value = false;
        }
    }

    /** Удаляет задачу по id. */
    async function deleteTask(id: number): Promise<boolean> {
        saving.value = true;
        error.value = null;

        try {
            await apiFetch(`/tasks/${id}`, {
                method: 'DELETE',
            });

            return true;
        } catch (err: unknown) {
            error.value = extractErrorMessage(err, 'Не удалось удалить задачу.');

            return false;
        } finally {
            saving.value = false;
        }
    }

    return {
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
    };
}
