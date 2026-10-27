const TOKEN_STORAGE_KEY = 'api_token';

export const clearApiToken = () => {
    localStorage.removeItem(TOKEN_STORAGE_KEY);
};

export const setApiToken = (token) => {
    if (!token) {
        clearApiToken();
        return;
    }

    localStorage.setItem(TOKEN_STORAGE_KEY, token);
};

export const readStoredApiToken = () => localStorage.getItem(TOKEN_STORAGE_KEY);

/**
 * Получает Passport Bearer:
 * 1) сессия main-app на том же gateway (`GET /get-api-token`);
 * 2) иначе токен из localStorage (ручной ввод).
 */
export const getApiToken = async () => {
    try {
        const response = await fetch('/get-api-token', {
            headers: {
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            const data = await response.json();
            if (data?.token) {
                setApiToken(data.token);
                return data.token;
            }
        }
    } catch {
        // Dev без gateway / нет сессии — fallback на localStorage.
    }

    const stored = readStoredApiToken();
    if (stored) {
        return stored;
    }

    throw new Error('Не удалось получить API-токен. Войдите в main-app или вставьте Bearer-токен.');
};

const authorizedJsonRequestWithToken = async (url, options, token) => fetch(url, {
    ...options,
    headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(options.headers ?? {}),
        Authorization: `Bearer ${token}`,
    },
});

export const authorizedJsonRequest = async (url, options = {}) => {
    let token = await getApiToken();
    let response = await authorizedJsonRequestWithToken(url, options, token);

    if ([401, 403].includes(response.status)) {
        clearApiToken();
        try {
            token = await getApiToken();
        } catch {
            return response;
        }

        response = await authorizedJsonRequestWithToken(url, options, token);

        if ([401, 403].includes(response.status)) {
            clearApiToken();
        }
    }

    return response;
};
