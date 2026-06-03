export const resolveSalesOutletsRoute = (routes, key) => {
    const url = routes?.[key];

    if (typeof url !== 'string' || url.trim() === '') {
        throw new Error(
            `Маршрут «${key}» не настроен. Обновите страницу (Ctrl+F5).`,
        );
    }

    return url;
};

export const routeWithUuid = (template, uuid) => {
    if (typeof template !== 'string' || ! template.includes('__uuid__')) {
        throw new Error('Некорректный шаблон URL для polling.');
    }

    if (! uuid) {
        throw new Error('Не получен идентификатор задачи.');
    }

    return template.replace('__uuid__', uuid);
};
