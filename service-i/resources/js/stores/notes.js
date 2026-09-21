/**
 * Pinia-store списка и CRUD заметок.
 *
 * TODO: query-параметры (filters, sort, pagination) намеренно не поддержаны.
 */
import { defineStore } from 'pinia';
import { ref } from 'vue';
import * as notesApi from '../api/notes';

export const useNotesStore = defineStore('notes', () => {
    const items = ref([]);
    const current = ref(null);
    const loading = ref(false);
    const error = ref(null);

    function clearError() {
        error.value = null;
    }

    /**
     * Загружает полный список заметок без фильтров/пагинации.
     */
    async function loadAll() {
        loading.value = true;
        error.value = null;

        try {
            items.value = await notesApi.fetchNotes();
        } catch (e) {
            error.value = e.response?.data?.message ?? e.message ?? 'Не удалось загрузить заметки';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Загружает одну заметку по id.
     *
     * @param {number|string} id
     */
    async function loadOne(id) {
        loading.value = true;
        error.value = null;

        try {
            current.value = await notesApi.fetchNote(id);
            return current.value;
        } catch (e) {
            error.value = e.response?.data?.message ?? e.message ?? 'Не удалось загрузить заметку';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Создаёт заметку и добавляет её в локальный список.
     *
     * @param {Record<string, unknown>} payload
     */
    async function create(payload) {
        loading.value = true;
        error.value = null;

        try {
            const note = await notesApi.createNote(payload);
            items.value = [note, ...items.value];
            current.value = note;
            return note;
        } catch (e) {
            error.value = formatValidationError(e) ?? e.message ?? 'Не удалось создать заметку';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Обновляет заметку и синхронизирует список.
     *
     * @param {number|string} id
     * @param {Record<string, unknown>} payload
     */
    async function update(id, payload) {
        loading.value = true;
        error.value = null;

        try {
            const note = await notesApi.updateNote(id, payload);
            current.value = note;
            const idx = items.value.findIndex((n) => Number(n.id) === Number(id));
            if (idx !== -1) {
                items.value[idx] = note;
            }
            return note;
        } catch (e) {
            error.value = formatValidationError(e) ?? e.message ?? 'Не удалось обновить заметку';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Удаляет заметку и убирает её из списка.
     *
     * @param {number|string} id
     */
    async function remove(id) {
        error.value = null;

        try {
            await notesApi.deleteNote(id);
            items.value = items.value.filter((n) => Number(n.id) !== Number(id));
            if (current.value && Number(current.value.id) === Number(id)) {
                current.value = null;
            }
        } catch (e) {
            error.value = e.response?.data?.message ?? e.message ?? 'Не удалось удалить заметку';
            throw e;
        }
    }

    return {
        items,
        current,
        loading,
        error,
        clearError,
        loadAll,
        loadOne,
        create,
        update,
        remove,
    };
});

/**
 * @param {unknown} e
 * @returns {string|null}
 */
function formatValidationError(e) {
    const errors = e?.response?.data?.errors;
    if (errors && typeof errors === 'object') {
        return Object.values(errors).flat().join(' ');
    }

    return e?.response?.data?.message ?? null;
}
