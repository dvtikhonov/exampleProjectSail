import type { ListTasksQuery, SortDirection, TaskSortField, TaskStatus } from '~/types/task';

/**
 * Преобразует route.query в параметры списка задач.
 */
export function parseTasksRouteQuery(query: Record<string, unknown>): ListTasksQuery {
    const status = String(query.status ?? '') as TaskStatus | '';
    const allowedStatuses: TaskStatus[] = ['pending', 'in_progress', 'completed'];

    const sort = String(query.sort ?? 'created_at') as TaskSortField;
    const allowedSort: TaskSortField[] = ['due_date', 'status', 'created_at'];

    const direction = String(query.direction ?? 'desc') as SortDirection;

    const page = Number.parseInt(String(query.page ?? '1'), 10);

    return {
        status: allowedStatuses.includes(status) ? status : '',
        search: String(query.search ?? ''),
        sort: allowedSort.includes(sort) ? sort : 'created_at',
        direction: direction === 'asc' ? 'asc' : 'desc',
        page: Number.isFinite(page) && page > 0 ? page : 1,
    };
}

/**
 * Собирает объект query для router из фильтров списка задач.
 */
export function tasksQueryToRouteQuery(query: ListTasksQuery): Record<string, string> {
    const routeQuery: Record<string, string> = {};

    if (query.status) {
        routeQuery.status = query.status;
    }

    if (query.search?.trim()) {
        routeQuery.search = query.search.trim();
    }

    if (query.sort && query.sort !== 'created_at') {
        routeQuery.sort = query.sort;
    }

    if (query.direction && query.direction !== 'desc') {
        routeQuery.direction = query.direction;
    }

    if (query.page && query.page > 1) {
        routeQuery.page = String(query.page);
    }

    return routeQuery;
}
