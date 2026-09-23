/**
 * Pinia-store списка и CRUD заметок.
 *
 * Список: фильтры / sort / limit-offset через GET /api/notes.
 * Defaults фильтров совпадают с IndexNoteRequest::filters().
 * AbortController: один активный list-запрос; устаревшие ответы игнорируются.
 */
import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import * as notesApi from '../api/notes';

export const useNotesStore = defineStore('notes', () => {
    const items = ref([]);
    const total = ref(0);
    const limit = ref(20);
    const offset = ref(0);
    const error = ref(null);
    const loading = ref(false);
    const current = ref(null);

    /** @type {import('vue').Ref<string>} */
    const q = ref('');
    /** @type {import('vue').Ref<string[]>} */
    const tags = ref([]);
    /** API-value: 'false' | 'true' | 'all' */
    const archived = ref('false');
    const sort = ref('-updated_at');

    /** @type {AbortController|null} */
    let listController = null;

    /** Есть ещё страницы (без !error — чтобы retry Load more работал). */
    const hasMore = computed(() => items.value.length < total.value);

    function clearError() {
        error.value = null;
    }

    /**
     * Параметры GET /api/notes из текущего состояния фильтров.
     *
     * @param {number} requestOffset
     * @returns {Record<string, string|number>}
     */
    function listParams(requestOffset) {
        /** @type {Record<string, string|number>} */
        const params = {
            archived: archived.value,
            sort: sort.value,
            limit: limit.value,
            offset: requestOffset,
        };

        const trimmedQ = q.value.trim();
        if (trimmedQ !== '') {
            params.q = trimmedQ;
        }

        if (tags.value.length > 0) {
            params.tags = tags.value.join(',');
        }

        return params;
    }

    /**
     * Первая страница: replace items. На ошибке — items=[], total=0.
     */
    async function loadFirst() {
        error.value = null;
        offset.value = 0;

        listController?.abort();
        const controller = new AbortController();
        listController = controller;

        loading.value = true;

        try {
            const data = await notesApi.fetchNotes(listParams(0), {
                signal: controller.signal,
            });

            if (listController !== controller) {
                return;
            }

            items.value = data.items;
            total.value = data.total;
            offset.value = 0;
        } catch (e) {
            if (listController !== controller) {
                return;
            }

            if (notesApi.isRequestAborted(e)) {
                return;
            }

            items.value = [];
            total.value = 0;
            error.value = formatListError(e);
            throw e;
        } finally {
            if (listController === controller) {
                loading.value = false;
            }
        }
    }

    /**
     * Следующая страница (offset = items.length). На ошибке items/total не трогаем.
     */
    async function loadMore() {
        if (loading.value || !hasMore.value) {
            return;
        }

        listController?.abort();
        const controller = new AbortController();
        listController = controller;

        const requestOffset = items.value.length;
        loading.value = true;
        error.value = null;

        try {
            const data = await notesApi.fetchNotes(listParams(requestOffset), {
                signal: controller.signal,
            });

            if (listController !== controller) {
                return;
            }

            items.value = [...items.value, ...data.items];
            total.value = data.total;
            offset.value = requestOffset;
        } catch (e) {
            if (listController !== controller) {
                return;
            }

            if (notesApi.isRequestAborted(e)) {
                return;
            }

            error.value = formatListError(e);
            throw e;
        } finally {
            if (listController === controller) {
                loading.value = false;
            }
        }
    }

    /**
     * Загружает одну заметку по id (не затрагивает list / AbortController списка).
     *
     * @param {number|string} id
     */
    async function loadOne(id) {
        error.value = null;

        try {
            current.value = await notesApi.fetchNote(id);
            return current.value;
        } catch (e) {
            error.value = e.response?.data?.message ?? e.message ?? 'Не удалось загрузить заметку';
            throw e;
        }
    }

    /**
     * Создаёт заметку (список обновится через loadFirst на Index).
     *
     * @param {Record<string, unknown>} payload
     */
    async function create(payload) {
        error.value = null;

        try {
            const note = await notesApi.createNote(payload);
            current.value = note;
            return note;
        } catch (e) {
            error.value = formatValidationError(e) ?? e.message ?? 'Не удалось создать заметку';
            throw e;
        }
    }

    /**
     * Обновляет заметку (список обновится через loadFirst на Index).
     *
     * @param {number|string} id
     * @param {Record<string, unknown>} payload
     */
    async function update(id, payload) {
        error.value = null;

        try {
            const note = await notesApi.updateNote(id, payload);
            current.value = note;
            return note;
        } catch (e) {
            error.value = formatValidationError(e) ?? e.message ?? 'Не удалось обновить заметку';
            throw e;
        }
    }

    /**
     * Удаляет заметку. Вызывающий Index делает loadFirst() после успеха.
     *
     * @param {number|string} id
     */
    async function remove(id) {
        error.value = null;

        try {
            await notesApi.deleteNote(id);
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
        total,
        limit,
        offset,
        error,
        loading,
        current,
        q,
        tags,
        archived,
        sort,
        hasMore,
        clearError,
        loadFirst,
        loadMore,
        loadOne,
        create,
        update,
        remove,
    };
});

/**
 * @param {unknown} e
 * @returns {string}
 */
function formatListError(e) {
    const status = e?.response?.status;
    if (status === 422) {
        return formatValidationError(e) ?? 'Некорректные параметры фильтра';
    }

    return e?.response?.data?.message ?? e?.message ?? 'Не удалось загрузить заметки';
}

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
