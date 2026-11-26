const MOSCOW_TIME_ZONE = 'Europe/Moscow';

/** Винительный падеж с предлогом: «в среду», «во вторник». */
const WEEKDAY_WHEN = {
    1: 'в понедельник',
    2: 'во вторник',
    3: 'в среду',
    4: 'в четверг',
    5: 'в пятницу',
    6: 'в субботу',
    7: 'в воскресенье',
};

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
 * Календарная дата Europe/Moscow в виде Y-m-d.
 * Совпадает с расчётом даты меню.
 *
 * @param {Date} now
 * @returns {string}
 */
function moscowIsoDate(now) {
    return new Intl.DateTimeFormat('en-CA', {
        timeZone: MOSCOW_TIME_ZONE,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(now);
}

/**
 * @param {string} isoDate
 * @param {number} days
 * @returns {string}
 */
function addIsoDays(isoDate, days) {
    const parts = parseIsoDateParts(isoDate);

    if (!parts) {
        return '';
    }

    const date = new Date(Date.UTC(Number(parts.year), Number(parts.month) - 1, Number(parts.day)));
    date.setUTCDate(date.getUTCDate() + days);

    const year = date.getUTCFullYear();
    const month = String(date.getUTCMonth() + 1).padStart(2, '0');
    const day = String(date.getUTCDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

/**
 * ISO-номер дня недели календарной даты (1 = понедельник … 7 = воскресенье).
 *
 * @param {string} isoDate
 * @returns {number|null}
 */
function isoWeekday(isoDate) {
    const parts = parseIsoDateParts(isoDate);

    if (!parts) {
        return null;
    }

    const date = new Date(Date.UTC(Number(parts.year), Number(parts.month) - 1, Number(parts.day)));
    const weekday = date.getUTCDay();

    return weekday === 0 ? 7 : weekday;
}

/**
 * Пятница, суббота и воскресенье: привоз в ближайший понедельник не называется «завтра»,
 * даже если понедельник выпадает на следующий день (заказ в воскресенье).
 *
 * @param {string} todayIso
 * @param {string} deliveryIso
 * @returns {boolean}
 */
function isWeekendMondayDelivery(todayIso, deliveryIso) {
    const todayWeekday = isoWeekday(todayIso);
    const deliveryWeekday = isoWeekday(deliveryIso);

    if (deliveryWeekday !== 1 || todayWeekday === null || todayWeekday < 5) {
        return false;
    }

    return deliveryIso === addIsoDays(todayIso, 8 - todayWeekday);
}

/**
 * Подпись окна доставки.
 * «Завтра» — только если дата доставки приходится на следующий день по Москве
 * и это не понедельник после заказа в пятницу или на выходных.
 *
 * @param {string|null|undefined} isoDate
 * @param {Date} [now]
 * @returns {string}
 */
export function formatDeliveryWindowCaption(isoDate, now = new Date()) {
    const parts = parseIsoDateParts(isoDate);

    if (!parts) {
        return '';
    }

    const deliveryIso = `${parts.year}-${parts.month}-${parts.day}`;
    const todayIso = moscowIsoDate(now);
    let when = WEEKDAY_WHEN[isoWeekday(deliveryIso)] ?? '';

    if (deliveryIso === todayIso) {
        when = 'сегодня';
    } else if (deliveryIso === addIsoDays(todayIso, 1) && !isWeekendMondayDelivery(todayIso, deliveryIso)) {
        when = 'завтра';
    }

    if (when === '') {
        return '';
    }

    const whenLabel = when.charAt(0).toUpperCase() + when.slice(1);

    return `${whenLabel} утром привезём с 9:00 до 12:00`;
}
