/**
 * Обёртка над window.Telegram.WebApp.
 * @see https://core.telegram.org/bots/webapps
 */

/**
 * @returns {object|null}
 */
export function getWebApp() {
    return window.Telegram?.WebApp ?? null;
}

/**
 * initData из Telegram WebApp или локальной заглушки.
 *
 * @returns {string}
 */
export function getInitData() {
    const webApp = getWebApp();
    const fromBridge = webApp?.initData ?? '';

    if (fromBridge) {
        return fromBridge;
    }

    const devInitData = window.__TELEGRAM_DEV_INIT_DATA__;

    if (typeof devInitData === 'string' && devInitData !== '') {
        return devInitData;
    }

    return '';
}

/**
 * @param {() => void} callback
 * @returns {() => void} cleanup
 */
export function bindBackButton(callback) {
    const webApp = getWebApp();

    if (!webApp?.BackButton) {
        return () => {};
    }

    webApp.BackButton.show();
    webApp.BackButton.onClick(callback);

    return () => {
        webApp.BackButton.offClick(callback);
        webApp.BackButton.hide();
    };
}

/**
 * Скрывает системную кнопку «Назад» Telegram WebApp.
 */
export function hideBackButton() {
    const webApp = getWebApp();

    if (webApp?.BackButton) {
        webApp.BackButton.hide();
    }
}

/**
 * Закрывает Mini App в клиенте Telegram.
 */
export function closeMaxApp() {
    const webApp = getWebApp();

    if (typeof webApp?.close === 'function') {
        webApp.close();
    }
}

/**
 * @returns {string}
 */
export function getPlatform() {
    return getWebApp()?.platform ?? 'unknown';
}

/**
 * start_param из initDataUnsafe / initData / tgWebAppStartParam / startapp.
 *
 * @returns {string}
 */
export function getStartParam() {
    const webApp = getWebApp();
    const fromUnsafe = webApp?.initDataUnsafe?.start_param;

    if (typeof fromUnsafe === 'string' && fromUnsafe !== '') {
        return fromUnsafe;
    }

    const initData = getInitData();

    if (initData) {
        const fromInitData = new URLSearchParams(initData).get('start_param');

        if (typeof fromInitData === 'string' && fromInitData !== '') {
            return fromInitData;
        }
    }

    const urlParams = new URLSearchParams(window.location.search);
    const fromUrl = urlParams.get('tgWebAppStartParam')
        ?? urlParams.get('WebAppStartParam')
        ?? urlParams.get('startapp');

    return typeof fromUrl === 'string' ? fromUrl : '';
}

/**
 * Отключает системные вертикальные свайпы Telegram (закрытие/сворачивание),
 * чтобы жесты доходили до внутренних scroll-контейнеров.
 */
export function disableVerticalSwipes() {
    const webApp = getWebApp();

    if (typeof webApp?.disableVerticalSwipes === 'function') {
        webApp.disableVerticalSwipes();
    }
}
