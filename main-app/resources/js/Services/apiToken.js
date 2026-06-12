const TOKEN_STORAGE_KEY = 'api_token';

export const clearApiToken = () => {
    localStorage.removeItem(TOKEN_STORAGE_KEY);
};

export const getApiToken = async () => {
    const response = await fetch('/get-api-token', {
        headers: {
            Accept: 'application/json',
        },
        credentials: 'same-origin',
    });

    if (! response.ok) {
        if ([401, 403].includes(response.status)) {
            clearApiToken();
        }

        throw new Error('Не удалось получить API-токен');
    }

    const data = await response.json();
    localStorage.setItem(TOKEN_STORAGE_KEY, data.token);

    return data.token;
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
        token = await getApiToken();
        response = await authorizedJsonRequestWithToken(url, options, token);

        if ([401, 403].includes(response.status)) {
            clearApiToken();
        }
    }

    return response;
};
