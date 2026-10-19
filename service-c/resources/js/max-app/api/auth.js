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
        // #region agent log
        const params = new URLSearchParams(initData);
        const endpoint = authEndpoint();
        const channel = getMessengerChannel();
        fetch('http://127.0.0.1:7407/ingest/f0ac76d6-9a52-46cc-8907-e183ecaefa5d',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'058a74'},body:JSON.stringify({sessionId:'058a74',runId:'pre-fix',hypothesisId:'D-E',location:'auth.js:authenticate:before',message:'telegram/max auth request',data:{channel,endpoint,initDataLength:initData.length,paramKeys:[...params.keys()],hasHash:params.has('hash'),hasSignature:params.has('signature'),hasUser:params.has('user'),authDate:params.get('auth_date'),platform:window.Telegram?.WebApp?.platform??null,tgVersion:window.Telegram?.WebApp?.version??null,initDataUnsafeKeys:Object.keys(window.Telegram?.WebApp?.initDataUnsafe??{})},timestamp:Date.now()})}).catch(()=>{});
        // #endregion

        try {
            const { data } = await client.post(endpoint, { init_data: initData });
            // #region agent log
            fetch('http://127.0.0.1:7407/ingest/f0ac76d6-9a52-46cc-8907-e183ecaefa5d',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'058a74'},body:JSON.stringify({sessionId:'058a74',runId:'pre-fix',hypothesisId:'A-B-C',location:'auth.js:authenticate:ok',message:'auth success',data:{channel,endpoint,hasToken:Boolean(data?.token),maxUserId:data?.user?.max_user_id??null},timestamp:Date.now()})}).catch(()=>{});
            // #endregion
            setAuthToken(data.token);

            return data;
        } catch (error) {
            // #region agent log
            const axiosErr = error && typeof error === 'object' && 'response' in error ? error : null;
            const respData = axiosErr?.response?.data ?? null;
            fetch('http://127.0.0.1:7407/ingest/f0ac76d6-9a52-46cc-8907-e183ecaefa5d',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'058a74'},body:JSON.stringify({sessionId:'058a74',runId:'pre-fix',hypothesisId:'A-B-C-D-E',location:'auth.js:authenticate:error',message:'auth failed',data:{channel,endpoint,status:axiosErr?.response?.status??null,apiMessage:respData?.message??null,debugReason:respData?.debug_reason??null,debugMeta:respData?.debug_meta??null},timestamp:Date.now()})}).catch(()=>{});
            // #endregion
            throw error;
        }
    })().finally(() => {
        authenticatePromise = null;
    });

    return authenticatePromise;
}

registerAuthenticateHandler(authenticate);
