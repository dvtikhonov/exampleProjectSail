/**
 * Загрузка, отправка и polling сообщений лички с пользователем через бота.
 */
import { fetchBotDmMessages, sendBotDmMessage } from '../api';
import { BOT_DM_CHAT_POLL_INTERVAL_MS } from '../constants/botDmChat';
import { usePolledChat } from './usePolledChat';

/**
 * @param {object} options
 * @param {import('vue').Ref<number|null>|(() => number|null)} options.maxUserId
 * @returns {object}
 */
export function useBotDmChat({ maxUserId }) {
    return usePolledChat({
        resolveId: () => {
            const value = typeof maxUserId === 'function' ? maxUserId() : maxUserId.value;
            const id = Number(value);

            return Number.isFinite(id) && id > 0 ? id : null;
        },
        fetch: fetchBotDmMessages,
        send: sendBotDmMessage,
        pollIntervalMs: BOT_DM_CHAT_POLL_INTERVAL_MS,
        clearComposerOnIdChange: true,
    });
}
