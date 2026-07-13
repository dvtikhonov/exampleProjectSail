/**
 * Глобальный перехват 401: сброс сессии и редирект на /login.
 */
export default defineNuxtPlugin(() => {
    const { handleUnauthorized } = useUnauthorizedHandler();

    const sanctumFetch = $fetch.create({
        onResponseError({ response, request }) {
            if (response.status !== 401) {
                return;
            }

            handleUnauthorized(String(request));
        },
    });

    return {
        provide: {
            sanctumFetch,
        },
    };
});
