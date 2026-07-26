/**
 * Авторизация mini-app: initData MAX Bridge → JWT.
 *
 * @typedef {import('./types.js').AuthResponseDto} AuthResponseDto
 */
import { client, registerAuthenticateHandler, setAuthToken } from './http';

/**
 * Подпись initData от MAX Bridge → JWT в sessionStorage.
 *
 * @param {string} initData
 * @returns {Promise<AuthResponseDto>}
 */
export async function authenticate(initData) {
    const { data } = await client.post('/max/auth', { init_data: initData });
    setAuthToken(data.token);

    return data;
}

registerAuthenticateHandler(authenticate);
