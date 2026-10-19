/**
 * Авторизация mini-app: initData Bridge → JWT (MAX или Telegram).
 *
 * @typedef {import('./types.js').AuthResponseDto} AuthResponseDto
 */
import { getMessengerChannel } from '../bridge';
import { client, registerAuthenticateHandler, setAuthToken } from './http';

/** @type {Promise<AuthResponseDto>|null} */
let authenticatePromise = null;

/**
 * @returns {'/max/auth'|'/telegram/auth'}
 */
function authEndpoint() {
    return getMessengerChannel() === 'telegram' ? '/telegram/auth' : '/max/auth';
}

/**
 * Подпись initData от Bridge → JWT в sessionStorage.
 * Single-flight: параллельные вызовы (initAuth×2, 401-reauth) делят один POST,
 * иначе сервер revokeNamedTokens отзывает только что выданный токен.
 *
 * @param {string} initData
 * @returns {Promise<AuthResponseDto>}
 */
export async function authenticate(initData) {
    if (authenticatePromise) {
        return authenticatePromise;
    }

    authenticatePromise = (async () => {
        const { data } = await client.post(authEndpoint(), { init_data: initData });
        setAuthToken(data.token);

        return data;
    })().finally(() => {
        authenticatePromise = null;
    });

    return authenticatePromise;
}

registerAuthenticateHandler(authenticate);
