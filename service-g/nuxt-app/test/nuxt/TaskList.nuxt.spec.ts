import { mountSuspended } from '@nuxt/test-utils/runtime';
import { describe, expect, it } from 'vitest';
import TaskList from '~/components/TaskList.vue';
import type { AuthUser } from '~/types/auth';
import type { Task } from '~/types/task';

const sampleUser: AuthUser = {
    id: 1,
    name: 'User',
    email: 'user@example.com',
    role: 'user',
};

const sampleTask: Task = {
    id: 10,
    title: 'Подготовить отчёт',
    description: 'Квартальный отчёт',
    due_date: '2026-12-31',
    status: 'pending',
    created_at: '2026-07-01T10:00:00.000000Z',
    updated_at: '2026-07-01T10:00:00.000000Z',
    user_id: 1,
};

describe('TaskList', () => {
    it('показывает empty state, когда задач нет', async () => {
        const wrapper = await mountSuspended(TaskList, {
            props: {
                tasks: [],
                loading: false,
                user: sampleUser,
            },
        });

        expect(wrapper.text()).toContain('Задач пока нет');
        expect(wrapper.text()).toContain('Создайте первую задачу или измените фильтры поиска.');
    });

    it('показывает ошибку API вместо empty state', async () => {
        const wrapper = await mountSuspended(TaskList, {
            props: {
                tasks: [],
                loading: false,
                listError: 'Сервер недоступен.',
                user: sampleUser,
            },
        });

        expect(wrapper.text()).toContain('Не удалось загрузить задачи');
        expect(wrapper.text()).toContain('Сервер недоступен.');
        expect(wrapper.text()).toContain('Повторить');
        expect(wrapper.text()).not.toContain('Задач пока нет');
    });

    it('эмитит retry при нажатии «Повторить»', async () => {
        const wrapper = await mountSuspended(TaskList, {
            props: {
                tasks: [],
                loading: false,
                listError: 'Сервер недоступен.',
                user: sampleUser,
            },
        });

        await wrapper.get('button').trigger('click');

        expect(wrapper.emitted('retry')).toHaveLength(1);
    });

    it('показывает skeleton при loading', async () => {
        const wrapper = await mountSuspended(TaskList, {
            props: {
                tasks: [],
                loading: true,
                user: sampleUser,
            },
        });

        expect(wrapper.find('[aria-busy="true"]').exists()).toBe(true);
        expect(wrapper.text()).not.toContain('Задач пока нет');
    });

    it('отображает список задач', async () => {
        const wrapper = await mountSuspended(TaskList, {
            props: {
                tasks: [sampleTask],
                loading: false,
                user: sampleUser,
            },
        });

        expect(wrapper.text()).toContain('Подготовить отчёт');
        expect(wrapper.text()).toContain('Квартальный отчёт');
        expect(wrapper.text()).toContain('Изменить');
        expect(wrapper.text()).toContain('Удалить');
    });

    it('скрывает кнопки управления для чужой задачи обычного пользователя', async () => {
        const foreignTask: Task = {
            ...sampleTask,
            id: 11,
            user_id: 99,
        };

        const wrapper = await mountSuspended(TaskList, {
            props: {
                tasks: [foreignTask],
                loading: false,
                user: sampleUser,
            },
        });

        expect(wrapper.text()).toContain('Подготовить отчёт');
        expect(wrapper.text()).not.toContain('Изменить');
        expect(wrapper.text()).not.toContain('Удалить');
    });

    it('блокирует кнопки управления чужой задачей для admin', async () => {
        const adminUser: AuthUser = {
            ...sampleUser,
            id: 2,
            role: 'admin',
        };
        const foreignTask: Task = {
            ...sampleTask,
            id: 11,
            user_id: 99,
            owner_name: 'Другой пользователь',
        };

        const wrapper = await mountSuspended(TaskList, {
            props: {
                tasks: [foreignTask],
                loading: false,
                user: adminUser,
            },
        });

        const buttons = wrapper.findAll('button');
        const editButton = buttons.find((button) => button.text() === 'Изменить');
        const deleteButton = buttons.find((button) => button.text() === 'Удалить');

        expect(editButton).toBeDefined();
        expect(deleteButton).toBeDefined();
        expect(editButton?.attributes('disabled')).toBeDefined();
        expect(deleteButton?.attributes('disabled')).toBeDefined();
    });
});
