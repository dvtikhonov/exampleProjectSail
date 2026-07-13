import { mountSuspended } from '@nuxt/test-utils/runtime';
import { describe, expect, it } from 'vitest';
import TaskForm from '~/components/TaskForm.vue';

describe('TaskForm', () => {
    it('показывает ошибку, если название короче 3 символов', async () => {
        const wrapper = await mountSuspended(TaskForm);

        await wrapper.find('#task-title').setValue('ab');
        await wrapper.find('form').trigger('submit.prevent');

        expect(wrapper.text()).toContain('Название должно содержать минимум 3 символа.');
    });

    it('не отправляет форму при коротком названии', async () => {
        const wrapper = await mountSuspended(TaskForm);

        await wrapper.find('#task-title').setValue('ab');
        await wrapper.find('form').trigger('submit.prevent');

        expect(wrapper.emitted('submit')).toBeUndefined();
    });

    it('показывает ошибку, если срок не указан', async () => {
        const wrapper = await mountSuspended(TaskForm);

        await wrapper.find('#task-title').setValue('Валидное название');
        await wrapper.find('form').trigger('submit.prevent');

        expect(wrapper.text()).toContain('Укажите срок выполнения.');
        expect(wrapper.emitted('submit')).toBeUndefined();
    });

    it('показывает ошибку для даты в прошлом', async () => {
        const wrapper = await mountSuspended(TaskForm);
        const yesterday = new Date();
        yesterday.setDate(yesterday.getDate() - 1);
        const pastDate = yesterday.toISOString().slice(0, 10);

        await wrapper.find('#task-title').setValue('Валидное название');
        await wrapper.find('#task-form-due-date').setValue(pastDate);
        await wrapper.find('form').trigger('submit.prevent');

        expect(wrapper.text()).toContain('Срок не может быть в прошлом.');
        expect(wrapper.emitted('submit')).toBeUndefined();
    });

    it('блокирует кнопку сохранения, пока данные не изменены', async () => {
        const wrapper = await mountSuspended(TaskForm, {
            props: {
                initial: {
                    title: 'Задача',
                    description: 'Описание',
                    due_date: '2026-12-31',
                    status: 'pending',
                },
                requireChanges: true,
                submitLabel: 'Сохранить',
            },
        });

        const submitButton = wrapper.get('button[type="submit"]');
        expect(submitButton.attributes('disabled')).toBeDefined();

        await wrapper.find('#task-title').setValue('Задача изменена');
        expect(submitButton.attributes('disabled')).toBeUndefined();
    });

    it('снова блокирует кнопку сохранения, если значения возвращены к исходным', async () => {
        const wrapper = await mountSuspended(TaskForm, {
            props: {
                initial: {
                    title: 'Задача',
                    description: null,
                    due_date: '2026-12-31',
                    status: 'pending',
                },
                requireChanges: true,
                submitLabel: 'Сохранить',
            },
        });

        const submitButton = wrapper.get('button[type="submit"]');

        await wrapper.find('#task-title').setValue('Другое название');
        expect(submitButton.attributes('disabled')).toBeUndefined();

        await wrapper.find('#task-title').setValue('Задача');
        expect(submitButton.attributes('disabled')).toBeDefined();
    });

    it('отправляет форму с валидными данными', async () => {
        const wrapper = await mountSuspended(TaskForm);
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        const dueDate = tomorrow.toISOString().slice(0, 10);

        await wrapper.find('#task-title').setValue('Новая задача');
        await wrapper.find('#task-description').setValue('Описание');
        await wrapper.find('#task-form-due-date').setValue(dueDate);
        await wrapper.find('form').trigger('submit.prevent');

        const emitted = wrapper.emitted('submit');
        expect(emitted).toHaveLength(1);
        expect(emitted?.[0]?.[0]).toEqual({
            title: 'Новая задача',
            description: 'Описание',
            due_date: dueDate,
            status: 'pending',
        });
    });
});
