/**
 * Только ФИО из first_name + last_name (без fallback на username/id).
 *
 * @param {{ first_name?: string|null, last_name?: string|null }} customer
 * @returns {string}
 */
export function formatCustomerFio(customer) {
    if (!customer || typeof customer !== 'object') {
        return '';
    }

    return [customer.first_name, customer.last_name]
        .filter((part) => typeof part === 'string' && part.trim() !== '')
        .map((part) => part.trim())
        .join(' ');
}

/**
 * ФИО / отображаемое имя потребителя (MAX user) из полей API.
 *
 * @param {{ first_name?: string|null, last_name?: string|null, username?: string|null, max_user_id?: number|null }} customer
 * @returns {string}
 */
export function formatCustomerName(customer) {
    if (!customer || typeof customer !== 'object') {
        return '';
    }

    const fio = formatCustomerFio(customer);

    if (fio !== '') {
        return fio;
    }

    if (typeof customer.username === 'string' && customer.username.trim() !== '') {
        return `@${customer.username.trim().replace(/^@/, '')}`;
    }

    if (customer.max_user_id != null && Number.isFinite(Number(customer.max_user_id))) {
        return `ID ${customer.max_user_id}`;
    }

    return '';
}
