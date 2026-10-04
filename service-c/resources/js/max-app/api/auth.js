/**
 * Авторизация mini-app: initData MAX Bridge → JWT.
 *
 * @typedef {import('./types.js').AuthResponseDto} AuthResponseDto
 */
import { client, registerAuthenticateHandler, setAuthToken } from './http';

/** @type {Promise<AuthResponseDto>|null} */
let authenticatePromise = null;

/**
 * Подпись initData от MAX Bridge → JWT в sessionStorage.
 * Single-flight: параллельные вызовы (initAuth×2, 401-reauth) делят один POST /max/auth,
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
        const { data } = await client.post('/max/auth', { init_data: initData });
        setAuthToken(data.token);

        return data;
    })().finally(() => {
        authenticatePromise = null;
    });

    return authenticatePromise;
}

registerAuthenticateHandler(authenticate);
