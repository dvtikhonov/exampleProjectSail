import { authorizedJsonRequest } from '@/Services/apiToken';

const gatewayOrigin = import.meta.env.VITE_GATEWAY_ORIGIN ?? 'http://localhost:8080';

export class SalesOutletValidationError extends Error {
    constructor(message, errors = {}) {
        super(message);
        this.name = 'SalesOutletValidationError';
        this.errors = errors;
    }
}

/**
 * @param {string} apiPrefix
 * @returns {{ updateHeadOrganization: Function, updateSalesOutlet: Function }}
 */
export const createSalesOutletsClient = (apiPrefix = '/api/a') => {
    const normalizedPrefix = apiPrefix.replace(/\/$/, '');

    const updateHeadOrganization = async ({ rowId, head_organization, head_organization_type }) => {
        const response = await authorizedJsonRequest(
            `${gatewayOrigin}${normalizedPrefix}/sales-outlets/${rowId}/head-organization`,
            {
                method: 'POST',
                body: JSON.stringify({
                    head_organization,
                    head_organization_type,
                }),
            },
        );

        if (! response.ok) {
            const message = response.status === 422
                ? 'Проверьте заполнение полей'
                : response.status === 401
                    ? 'Сессия истекла. Обновите страницу и войдите снова.'
                    : 'Не удалось сохранить головную организацию';

            throw new Error(message);
        }

        if (! response.headers.get('content-type')?.includes('application/json')) {
            throw new Error('Сервис вернул некорректный ответ');
        }

        return response.json();
    };

    const updateSalesOutlet = async (rowId, payload) => {
        const response = await authorizedJsonRequest(
            `${gatewayOrigin}${normalizedPrefix}/sales-outlets/${rowId}`,
            {
                method: 'PATCH',
                body: JSON.stringify(payload),
            },
        );

        if (! response.ok) {
            const data = response.headers.get('content-type')?.includes('application/json')
                ? await response.json()
                : {};

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

        if (! response.headers.get('content-type')?.includes('application/json')) {
            throw new Error('Сервис вернул некорректный ответ');
        }

        return response.json();
    };

    return {
        updateHeadOrganization,
        updateSalesOutlet,
    };
};

const defaultClient = createSalesOutletsClient('/api/a');

export const updateHeadOrganization = defaultClient.updateHeadOrganization;
export const updateSalesOutlet = defaultClient.updateSalesOutlet;
