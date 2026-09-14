const WEEKDAY_SHORT_FORMATTER = new Intl.DateTimeFormat('ru-RU', {
    weekday: 'short',
});

/**
 * Парсит ISO-дату Y-m-d в локальный Date (без сдвига TZ).
 *
 * @param {string|null|undefined} isoDate
 * @returns {{ year: string, month: string, day: string, date: Date }|null}
 */
function parseIsoDateParts(isoDate) {
    if (typeof isoDate !== 'string' || isoDate.trim() === '') {
        return null;
    }

    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(isoDate.trim());

    if (!match) {
        return null;
    }

    const year = Number(match[1]);
    const month = Number(match[2]);
    const day = Number(match[3]);

    return {
        year: match[1],
        month: match[2],
        day: match[3],
        date: new Date(year, month - 1, day),
    };
}

/**
 * Форматирует ISO-дату Y-m-d в ДД.ММ.ГГГГ.
 *
 * @param {string|null|undefined} isoDate
 * @returns {string}
 */
export function formatIsoDateRu(isoDate) {
    const parts = parseIsoDateParts(isoDate);

    if (!parts) {
        return typeof isoDate === 'string' ? isoDate : '';
    }

    return `${parts.day}.${parts.month}.${parts.year}`;
}

/**
 * Подпись окна доставки: «доставка пн. 3.08.2026 с 9:00 до 12:00».
 *
 * @param {string|null|undefined} isoDate
 * @returns {string}
 */
export function formatDeliveryWindowCaption(isoDate) {
    const parts = parseIsoDateParts(isoDate);

    if (!parts) {
        return '';
    }

    const weekdayRaw = WEEKDAY_SHORT_FORMATTER.format(parts.date).replace(/\.$/, '');
    const weekday = `${weekdayRaw.toLowerCase()}.`;
    const day = String(Number(parts.day));

    return `доставка ${weekday} ${day}.${parts.month}.${parts.year} с 9:00 до 12:00`;
}
