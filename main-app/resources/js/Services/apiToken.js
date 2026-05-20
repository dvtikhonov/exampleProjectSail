const TOKEN_STORAGE_KEY = 'api_token';

export const clearApiToken = () => {
    localStorage.removeItem(TOKEN_STORAGE_KEY);
};

export const getApiToken = async () => {
    const storedToken = localStorage.getItem(TOKEN_STORAGE_KEY);

    if (storedToken) {
        return storedToken;
    }

    const response = await fetch('/get-api-token', {
        headers: {
            Accept: 'application/json',
        },
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

export const authorizedJsonRequest = async (url, options = {}) => {
    const token = await getApiToken();

    const response = await fetch(url, {
        ...options,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(options.headers ?? {}),
            Authorization: `Bearer ${token}`,
        },
    });

    if ([401, 403].includes(response.status)) {
        clearApiToken();
    }

    return response;
};
