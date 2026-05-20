import { authorizedJsonRequest } from '@/Services/apiToken';

const gatewayOrigin = import.meta.env.VITE_GATEWAY_ORIGIN ?? 'http://localhost:8080';

export const updateHeadOrganization = async ({ rowId, head_organization, head_organization_type }) => {
    const response = await authorizedJsonRequest(`${gatewayOrigin}/api/a/sales-outlets/${rowId}/head-organization`, {
        method: 'POST',
        body: JSON.stringify({
            head_organization,
            head_organization_type,
        }),
    });

    if (! response.ok) {
        const message = response.status === 422
            ? 'Проверьте заполнение полей'
            : 'Не удалось сохранить головную организацию';

        throw new Error(message);
    }

    if (! response.headers.get('content-type')?.includes('application/json')) {
        throw new Error('Сервис вернул некорректный ответ');
    }

    return response.json();
};
