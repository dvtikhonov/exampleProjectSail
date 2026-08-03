/**
 * Форматирует ISO-дату Y-m-d в ДД.ММ.ГГГГ.
 *
 * @param {string|null|undefined} isoDate
 * @returns {string}
 */
export function formatIsoDateRu(isoDate) {
    if (typeof isoDate !== 'string' || isoDate.trim() === '') {
        return '';
    }

    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(isoDate.trim());

    if (!match) {
        return isoDate;
    }

    return `${match[3]}.${match[2]}.${match[1]}`;
}
