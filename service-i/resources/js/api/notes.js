/**
 * HTTP-клиент CRUD заметок (/api/notes).
 *
 * Query-параметры (фильтры/сортировка/пагинация) намеренно не поддержаны —
 * см. TODO в Pinia-store.
 */
import axios from 'axios';

const api = axios.create({
    baseURL: '/api',
    headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

/**
 * @returns {Promise<Array<Record<string, unknown>>>}
 */
export async function fetchNotes() {
    const { data } = await api.get('/notes');

    return Array.isArray(data) ? data : (data?.data ?? []);
}

/**
 * @param {number|string} id
 * @returns {Promise<Record<string, unknown>>}
 */
export async function fetchNote(id) {
    const { data } = await api.get(`/notes/${id}`);

    return data?.data ?? data;
}

/**
 * @param {Record<string, unknown>} payload
 * @returns {Promise<Record<string, unknown>>}
 */
export async function createNote(payload) {
    const { data } = await api.post('/notes', payload);

    return data?.data ?? data;
}

/**
 * @param {number|string} id
 * @param {Record<string, unknown>} payload
 * @returns {Promise<Record<string, unknown>>}
 */
export async function updateNote(id, payload) {
    const { data } = await api.put(`/notes/${id}`, payload);

    return data?.data ?? data;
}

/**
 * @param {number|string} id
 * @returns {Promise<void>}
 */
export async function deleteNote(id) {
    await api.delete(`/notes/${id}`);
}
