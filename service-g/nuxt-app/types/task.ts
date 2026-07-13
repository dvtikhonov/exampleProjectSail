export type TaskStatus = 'pending' | 'in_progress' | 'completed';

export type TaskSortField = 'due_date' | 'status' | 'created_at';

export type SortDirection = 'asc' | 'desc';

export interface Task {
    id: number;
    title: string;
    description: string | null;
    due_date: string | null;
    status: TaskStatus;
    created_at: string;
    updated_at: string;
    user_id: number;
    owner_name?: string | null;
}

export interface TaskPaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export interface PaginatedTasksResponse {
    data: Task[];
    meta: TaskPaginationMeta;
}

export interface TaskResponse {
    data: Task;
}

export interface ListTasksQuery {
    status?: TaskStatus | '';
    search?: string;
    sort?: TaskSortField;
    direction?: SortDirection;
    page?: number;
    per_page?: number;
}

export interface TaskPayload {
    title: string;
    description?: string | null;
    due_date: string;
    status: TaskStatus;
}

export interface TaskToolbarFilters {
    status: TaskStatus | '';
    search: string;
    sort: TaskSortField;
    direction: SortDirection;
}

export const TASK_STATUS_OPTIONS: { value: TaskStatus; label: string }[] = [
    { value: 'pending', label: 'Ожидает' },
    { value: 'in_progress', label: 'В работе' },
    { value: 'completed', label: 'Выполнено' },
];

export const TASK_SORT_OPTIONS: { value: TaskSortField; label: string }[] = [
    { value: 'created_at', label: 'Дата создания' },
    { value: 'due_date', label: 'Срок' },
    { value: 'status', label: 'Статус' },
];
