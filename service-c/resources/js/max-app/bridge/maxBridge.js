/**
 * Обёртка над window.WebApp (MAX Bridge).
 * @see https://dev.max.ru/docs/webapps/bridge
 */

/**
 * @returns {object|null}
 */
export function getWebApp() {
    return window.WebApp ?? null;
}

/**
 * initData из Bridge или из hash (#WebAppData=...).
 *
 * @returns {string}
 */
export function getInitData() {
    const webApp = getWebApp();
    const fromBridge = webApp?.initData ?? '';

    if (fromBridge) {
        return fromBridge;
    }

    const devInitData = window.__MAX_DEV_INIT_DATA__;

    if (typeof devInitData === 'string' && devInitData !== '') {
        return devInitData;
    }

    const hash = window.location.hash.replace(/^#/, '');

    if (!hash) {
        return '';
    }

    const params = new URLSearchParams(hash);

    if (params.has('WebAppData')) {
        return params.get('WebAppData') ?? '';
    }

    if (hash.includes('auth_date=') || hash.includes('user=')) {
        return hash;
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

export function hideBackButton() {
    const webApp = getWebApp();

    if (webApp?.BackButton) {
        webApp.BackButton.hide();
    }
}

/**
 * Закрывает mini-app в клиенте Max (важно для корректного повторного открытия на desktop).
 */
export function closeMaxApp() {
    if (typeof window.__MAX_CLOSE_DESKTOP_APP__ === 'function') {
        window.__MAX_CLOSE_DESKTOP_APP__();

        return;
    }

    const webApp = getWebApp();

    if (typeof webApp?.close === 'function') {
        webApp.close();
    }
}

/**
 * @returns {string}
 */
export function getPlatform() {
    return getWebApp()?.platform ?? 'web';
}
