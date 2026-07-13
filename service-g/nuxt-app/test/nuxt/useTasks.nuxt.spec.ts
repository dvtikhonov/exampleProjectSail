import { mockNuxtImport } from '@nuxt/test-utils/runtime';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { buildTasksQueryString, useTasks } from '~/composables/useTasks';
import type { PaginatedTasksResponse } from '~/types/task';

const { apiFetchMock } = vi.hoisted(() => ({
    apiFetchMock: vi.fn(),
}));

mockNuxtImport('useApi', () => {
    return () => ({
        apiFetch: apiFetchMock,
        extractErrorMessage: (_error: unknown, fallback: string) => fallback,
    });
});

describe('buildTasksQueryString', () => {
    it('возвращает пустую строку без параметров', () => {
        expect(buildTasksQueryString()).toBe('');
    });

    it('собирает query-string из фильтров и пагинации', () => {
        const queryString = buildTasksQueryString({
            status: 'pending',
            search: 'отчёт',
            sort: 'due_date',
            direction: 'asc',
            page: 2,
            per_page: 25,
        });

        expect(queryString).toBe('?status=pending&search=%D0%BE%D1%82%D1%87%D1%91%D1%82&sort=due_date&direction=asc&page=2&per_page=25');
    });

    it('не добавляет page=1 и per_page=5 по умолчанию', () => {
        const queryString = buildTasksQueryString({
            status: 'completed',
            page: 1,
            per_page: 5,
        });

        expect(queryString).toBe('?status=completed');
    });
});

describe('useTasks', () => {
    beforeEach(() => {
        apiFetchMock.mockReset();
        clearNuxtState();
    });

    it('fetchTasks вызывает apiFetch с корректным query-string', async () => {
        const response: PaginatedTasksResponse = {
            data: [],
            meta: {
                current_page: 1,
                last_page: 1,
                per_page: 15,
                total: 0,
            },
        };

        apiFetchMock.mockResolvedValue(response);

        const { fetchTasks } = useTasks();

        await fetchTasks({
            search: 'demo',
            sort: 'status',
            direction: 'desc',
        });

        expect(apiFetchMock).toHaveBeenCalledWith('/tasks?search=demo&sort=status&direction=desc');
    });

    it('fetchTasks сохраняет data и meta в состоянии', async () => {
        const response: PaginatedTasksResponse = {
            data: [
                {
                    id: 1,
                    title: 'Задача',
                    description: null,
                    due_date: null,
                    status: 'pending',
                    created_at: '2026-07-01T10:00:00.000000Z',
                    updated_at: '2026-07-01T10:00:00.000000Z',
                    user_id: 1,
                },
            ],
            meta: {
                current_page: 1,
                last_page: 3,
                per_page: 15,
                total: 42,
            },
        };

        apiFetchMock.mockResolvedValue(response);

        const { fetchTasks, tasks, pagination, loading, listError, error } = useTasks();

        await fetchTasks();

        expect(tasks.value).toEqual(response.data);
        expect(pagination.value).toEqual(response.meta);
        expect(loading.value).toBe(false);
        expect(listError.value).toBeNull();
        expect(error.value).toBeNull();
    });

    it('fetchTasks сохраняет listError при ошибке API', async () => {
        apiFetchMock.mockRejectedValue({
            data: { message: 'Сервер недоступен.' },
        });

        const { fetchTasks, tasks, loading, listError, error } = useTasks();

        await fetchTasks();

        expect(tasks.value).toEqual([]);
        expect(loading.value).toBe(false);
        expect(listError.value).toBe('Не удалось загрузить задачи.');
        expect(error.value).toBeNull();
    });
});
