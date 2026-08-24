/**
 * API управления доступом AI к базе для роли max_manager.
 *
 * @typedef {import('./types.js').AiAccessStatusDto} AiAccessStatusDto
 */
import { client } from './http';

/**
 * Получает текущий статус доступа AI.
 *
 * @returns {Promise<AiAccessStatusDto>}
 */
export async function fetchAiAccessStatus() {
    const { data } = await client.get('/food/admin/ai-access');

    return {
        enabled: Boolean(data.enabled),
        active_max_user_id: Number.isFinite(Number(data.active_max_user_id))
            ? Number(data.active_max_user_id)
            : null,
        expires_at: typeof data.expires_at === 'string' && data.expires_at !== ''
            ? data.expires_at
            : null,
    };
}

/**
 * Переключает доступ AI и возвращает обновлённый статус.
 *
 * @returns {Promise<AiAccessStatusDto>}
 */
export async function toggleAiAccess() {
    const { data } = await client.post('/food/admin/ai-access/toggle');

    return {
        enabled: Boolean(data.enabled),
        active_max_user_id: Number.isFinite(Number(data.active_max_user_id))
            ? Number(data.active_max_user_id)
            : null,
        expires_at: typeof data.expires_at === 'string' && data.expires_at !== ''
            ? data.expires_at
            : null,
    };
}
