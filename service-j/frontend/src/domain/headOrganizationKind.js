/**
 * Вид головной организации: код → русская метка.
 * Контракт /api/sales-outlets принимает метки (ООО, ИП, АО, СПК), не коды.
 * Порядок записей задаёт список в селекте; первая метка — запасной вид.
 */
const headOrganizationKindEntries = Object.freeze([
    ['ooo', 'ООО'],
    ['ip', 'ИП'],
    ['ao', 'АО'],
    ['spk', 'СПК'],
]);

/** @type {Readonly<Record<string, string>>} */
export const headOrganizationKindLabels = Object.freeze(Object.fromEntries(headOrganizationKindEntries));

/** @type {readonly string[]} */
export const headOrganizationKinds = Object.freeze(
    headOrganizationKindEntries.map(([, label]) => label),
);

/**
 * Проверяет, что значение уже является меткой (ООО, ИП, АО, СПК).
 *
 * @param {unknown} value
 * @returns {boolean}
 */
export const isHeadOrganizationKindLabel = (value) => headOrganizationKinds.includes(value);

/**
 * Приводит код (ooo) или метку (ООО) к метке.
 * Неизвестное значение заменяется на fallback: пустая строка в таблице,
 * первая метка в модалке и в форме poptip.
 *
 * @param {unknown} value
 * @param {string} [fallback]
 * @returns {string}
 */
export const normalizeHeadOrganizationKind = (value, fallback = '') => {
    const normalizedValue = String(value ?? '').trim();

    if (isHeadOrganizationKindLabel(normalizedValue)) {
        return normalizedValue;
    }

    return headOrganizationKindLabels[normalizedValue.toLowerCase()] ?? fallback;
};

/**
 * @param {string} name
 * @returns {string|undefined}
 */
const matchedKindPrefix = (name) => headOrganizationKinds.find((kind) => name.startsWith(`${kind} `));

/**
 * Снимает префикс вида («ООО ») с названия. Без префикса возвращает строку как есть.
 *
 * @param {unknown} value
 * @returns {string}
 */
export const stripHeadOrganizationKindPrefix = (value) => {
    const trimmedValue = String(value ?? '').trim();
    const matchedKind = matchedKindPrefix(trimmedValue);

    return matchedKind
        ? trimmedValue.slice(matchedKind.length).trim()
        : trimmedValue;
};

/**
 * Показывает «Вид Название», если вид распознан и префикса ещё нет.
 * Нераспознанный вид оставляет название без префикса.
 *
 * @param {unknown} name
 * @param {unknown} type
 * @returns {string}
 */
export const formatHeadOrganizationDisplay = (name, type) => {
    const trimmedName = String(name ?? '').trim();
    const currentKind = normalizeHeadOrganizationKind(type, '');

    if (trimmedName === '' || currentKind === '' || matchedKindPrefix(trimmedName)) {
        return trimmedName;
    }

    return `${currentKind} ${trimmedName}`;
};

/**
 * Разбирает отображаемое название для формы: вид из префикса,
 * иначе из organizationType с заданным fallback.
 *
 * @param {unknown} value
 * @param {unknown} organizationType
 * @param {string} [fallbackKind]
 * @returns {{ kind: string, name: string }}
 */
export const parseHeadOrganization = (value, organizationType, fallbackKind = headOrganizationKinds[0]) => {
    const trimmedValue = String(value ?? '').trim();
    const matchedKind = matchedKindPrefix(trimmedValue);

    if (!matchedKind) {
        return {
            kind: normalizeHeadOrganizationKind(organizationType, fallbackKind),
            name: trimmedValue,
        };
    }

    return {
        kind: matchedKind,
        name: stripHeadOrganizationKindPrefix(trimmedValue),
    };
};
