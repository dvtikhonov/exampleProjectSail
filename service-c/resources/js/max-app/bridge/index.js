/**
 * Channel-aware facade: MAX Bridge или Telegram.WebApp.
 * Импортируйте отсюда вместо maxBridge / telegramBridge напрямую.
 */
import { isTelegramChannel } from './messengerChannel';
import * as maxBridge from './maxBridge';
import * as telegramBridge from './telegramBridge';

function impl() {
    return isTelegramChannel() ? telegramBridge : maxBridge;
}

/** @returns {object|null} */
export function getWebApp() {
    return impl().getWebApp();
}

/** @returns {string} */
export function getInitData() {
    return impl().getInitData();
}

/**
 * @param {() => void} callback
 * @returns {() => void}
 */
export function bindBackButton(callback) {
    return impl().bindBackButton(callback);
}

export function hideBackButton() {
    impl().hideBackButton();
}

export function closeMaxApp() {
    impl().closeMaxApp();
}

/** @returns {string} */
export function getPlatform() {
    return impl().getPlatform();
}

/** @returns {string} */
export function getStartParam() {
    return impl().getStartParam();
}

export function disableVerticalSwipes() {
    impl().disableVerticalSwipes();
}

/**
 * Telegram desktop: выйти из fullscreen (movable SeparatePanel).
 * На MAX — no-op.
 *
 * @returns {() => void}
 */
export function ensureWindowedDesktop() {
    if (!isTelegramChannel()) {
        return () => {};
    }

    return telegramBridge.ensureWindowedDesktop();
}

/**
 * Telegram: синхронизация safe-area / content-safe-area CSS vars.
 * На MAX — no-op (достаточно env(safe-area-inset-*)).
 *
 * @returns {() => void}
 */
export function syncSafeAreaInsets() {
    if (!isTelegramChannel()) {
        return () => {};
    }

    return telegramBridge.syncTelegramSafeAreaInsets();
}

export { getMessengerChannel, isTelegramChannel } from './messengerChannel';
