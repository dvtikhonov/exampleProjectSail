/**
 * HTTP-клиент CRUD заметок (/api/notes).
 *
 * GET /notes принимает query: q, tags (CSV), archived, sort, limit, offset.
 * Ответ списка: envelope { items, total, limit, offset }.
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
 * @param {Record<string, unknown>} [params]
 * @param {{ signal?: AbortSignal }} [options]
 * @returns {Promise<{ items: Array<Record<string, unknown>>, total: number, limit: number, offset: number }>}
 */
export async function fetchNotes(params = {}, options = {}) {
    const { data } = await api.get('/notes', {
        params,
        signal: options.signal,
    });

    return {
        items: Array.isArray(data?.items) ? data.items : [],
        total: Number(data?.total ?? 0),
        limit: Number(data?.limit ?? 20),
        offset: Number(data?.offset ?? 0),
    };
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

/**
 * @param {unknown} e
 * @returns {boolean}
 */
export function isRequestAborted(e) {
    return (
        axios.isCancel?.(e) === true
        || e?.code === 'ERR_CANCELED'
        || e?.name === 'CanceledError'
        || e?.name === 'AbortError'
    );
}
