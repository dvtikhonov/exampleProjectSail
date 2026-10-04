/**
 * Админ: личка с пользователем MAX через бота (max_manager).
 *
 * @typedef {import('./types.js').ManualOrderUserDto} ManualOrderUserDto
 * @typedef {import('./types.js').BotDmMessageDto} BotDmMessageDto
 */
import { client } from './http';

/**
 * Поиск пользователей MAX для выбора собеседника.
 *
 * @param {{ q?: string, perPage?: number, signal?: AbortSignal }} [options]
 * @returns {Promise<ManualOrderUserDto[]>}
 */
export async function fetchBotDmUsers({ q = '', perPage = 30, signal } = {}) {
    const params = { per_page: perPage };

    if (typeof q === 'string' && q.trim() !== '') {
        params.q = q.trim();
    }

    const { data } = await client.get('/food/admin/bot-dm/users', { params, signal });

    if (Array.isArray(data.users)) {
        return data.users;
    }

    if (Array.isArray(data)) {
        return data;
    }

    return [];
}

/**
 * Лента сообщений лички с пользователем.
 *
 * @param {number} maxUserId
 * @param {{ afterId?: number|null, limit?: number, signal?: AbortSignal }} [options]
 * @returns {Promise<BotDmMessageDto[]>}
 */
export async function fetchBotDmMessages(maxUserId, { afterId = null, limit = 50, signal } = {}) {
    const params = { limit };

    if (afterId !== null) {
        params.after_id = afterId;
    }

    const { data } = await client.get(`/food/admin/bot-dm/${maxUserId}/messages`, {
        params,
        signal,
    });

    return Array.isArray(data.messages) ? data.messages : [];
}

/**
 * Отправляет текст пользователю в MAX и сохраняет сообщение в историю.
 *
 * @param {number} maxUserId
 * @param {string} body
 * @param {{ signal?: AbortSignal }} [options]
 * @returns {Promise<BotDmMessageDto>}
 */
export async function sendBotDmMessage(maxUserId, body, { signal } = {}) {
    const { data } = await client.post(
        `/food/admin/bot-dm/${maxUserId}/messages`,
        { body },
        { signal },
    );

    return data.message;
}
