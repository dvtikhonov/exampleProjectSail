/**
 * Канал mini-app: MAX или Telegram.
 * Задаётся в Blade до загрузки Vite-бандла (`window.__MESSENGER_CHANNEL__`).
 */

/** @typedef {'max'|'telegram'} MessengerChannel */

/**
 * @returns {MessengerChannel}
 */
export function getMessengerChannel() {
    const raw = typeof window !== 'undefined'
        ? window.__MESSENGER_CHANNEL__
        : undefined;

    return raw === 'telegram' ? 'telegram' : 'max';
}

/**
 * @returns {boolean}
 */
export function isTelegramChannel() {
    return getMessengerChannel() === 'telegram';
}
