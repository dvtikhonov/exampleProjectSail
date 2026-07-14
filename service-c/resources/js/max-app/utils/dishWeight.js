/**
 * Форматирование веса/объёма блюда для UI.
 *
 * @param {{ weight?: string|number|null, weight_unit_label?: string|null }|null|undefined} item
 * @returns {string}
 */
export function formatDishWeight(item) {
    if (item?.weight == null || item.weight === '') {
        return '';
    }

    const weight = String(Number.parseInt(String(item.weight), 10));
    const unitLabel = item.weight_unit_label ?? '';

    return unitLabel ? `${weight} ${unitLabel}` : weight;
}
