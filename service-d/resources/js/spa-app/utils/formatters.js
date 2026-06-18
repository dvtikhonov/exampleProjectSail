/**
 * Утилиты форматирования чисел и дат для UI (локаль ru-RU).
 */

/**
 * @param {number | string | null | undefined} value
 * @returns {string}
 */
export function formatRating(value) {
    if (value === null || value === undefined) {
        return '—';
    }

    return Number(value).toFixed(1);
}

/**
 * @param {number | null | undefined} value
 * @returns {string}
 */
export function formatCount(value) {
    if (value === null || value === undefined) {
        return '—';
    }

    return value.toLocaleString('ru-RU');
}

/**
 * @param {string | null | undefined} iso
 * @returns {string}
 */
export function formatDate(iso) {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}
