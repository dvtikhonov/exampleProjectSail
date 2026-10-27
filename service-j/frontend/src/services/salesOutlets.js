import { authorizedJsonRequest } from '@/services/apiToken';

export class SalesOutletValidationError extends Error {
    constructor(message, errors = {}) {
        super(message);
        this.name = 'SalesOutletValidationError';
        this.errors = errors;
    }
}

const appendColumns = (params, columns) => {
    if (!Array.isArray(columns)) {
        return;
    }

    columns.forEach((column) => {
        params.append('columns[]', column);
    });
};

const appendColumnFilters = (params, columnFilters) => {
    if (!columnFilters || typeof columnFilters !== 'object') {
        return;
    }

    Object.entries(columnFilters).forEach(([key, value]) => {
        const trimmed = String(value ?? '').trim();
        if (trimmed !== '') {
            params.append(`column_filters[${key}]`, trimmed);
        }
    });
};

/**
 * @param {string} apiPrefix
 */
export const createSalesOutletsClient = (apiPrefix = '/api/j') => {
    const normalizedPrefix = apiPrefix.replace(/\/$/, '');

    const parseJsonOrEmpty = async (response) => {
        if (!response.headers.get('content-type')?.includes('application/json')) {
            return {};
        }

        return response.json();
    };

    const index = async (query = {}) => {
        const params = new URLSearchParams();

        if (query.search) {
            params.set('search', query.search);
        }
        if (query.status) {
            params.set('status', query.status);
        }
        if (query.sort) {
            params.set('sort', query.sort);
        }
        if (query.direction) {
            params.set('direction', query.direction);
        }
        if (query.page) {
            params.set('page', String(query.page));
        }
        if (query.per_page) {
            params.set('per_page', String(query.per_page));
        }

        appendColumns(params, query.columns);
        appendColumnFilters(params, query.column_filters);

        const qs = params.toString();
        const response = await authorizedJsonRequest(
            `${normalizedPrefix}/sales-outlets${qs ? `?${qs}` : ''}`,
            { method: 'GET' },
        );

        if (!response.ok) {
            if (response.status === 401) {
                throw new Error('Сессия истекла. Обновите страницу и войдите снова.');
            }

            throw new Error('Не удалось загрузить объекты продаж');
        }

        return response.json();
    };

    const updateHeadOrganization = async ({ rowId, head_organization, head_organization_type }) => {
        const response = await authorizedJsonRequest(
            `${normalizedPrefix}/sales-outlets/${rowId}/head-organization`,
            {
                method: 'POST',
                body: JSON.stringify({
                    head_organization,
                    head_organization_type,
                }),
            },
        );

        if (!response.ok) {
            const message = response.status === 422
                ? 'Проверьте заполнение полей'
                : response.status === 401
                    ? 'Сессия истекла. Обновите страницу и войдите снова.'
                    : 'Не удалось сохранить головную организацию';

            throw new Error(message);
        }

        return response.json();
    };

    const updateSalesOutlet = async (rowId, payload) => {
        const response = await authorizedJsonRequest(
            `${normalizedPrefix}/sales-outlets/${rowId}`,
            {
                method: 'PATCH',
                body: JSON.stringify(payload),
            },
        );

        if (!response.ok) {
            const data = await parseJsonOrEmpty(response);

            if (response.status === 422) {
                throw new SalesOutletValidationError(
                    data.message ?? 'Проверьте заполнение полей',
                    data.errors ?? {},
                );
            }

            if (response.status === 401) {
                throw new Error('Сессия истекла. Обновите страницу и войдите снова.');
            }

            throw new Error(data.message ?? 'Не удалось сохранить объект продаж');
        }

        return response.json();
    };

    const deleteSalesOutlet = async (rowId) => {
        const response = await authorizedJsonRequest(
            `${normalizedPrefix}/sales-outlets/${rowId}`,
            { method: 'DELETE' },
        );

        if (response.status === 204) {
            return;
        }

        if (response.status === 401) {
            throw new Error('Сессия истекла. Обновите страницу и войдите снова.');
        }

        if (response.status === 404) {
            throw new Error('Объект продаж не найден');
        }

        throw new Error('Не удалось удалить объект продаж');
    };

    return {
        index,
        updateHeadOrganization,
        updateSalesOutlet,
        deleteSalesOutlet,
    };
};
