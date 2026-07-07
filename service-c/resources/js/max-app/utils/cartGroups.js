/**
 * Группирует позиции корзины: обычные блюда отдельно, компоненты комбо по combo_ref.
 */

/**
 * @param {object|null} cart
 * @returns {Array<object>}
 */
export function buildCartGroups(cart) {
    if (!cart?.items) {
        return [];
    }

    const groups = [];
    const comboGroups = new Map();

    for (const item of cart.items) {
        if (!item.combo_ref) {
            groups.push({
                type: 'item',
                key: `item:${item.id}`,
                id: item.id,
                item,
                items: [item],
                quantity: item.quantity,
                lineTotal: item.line_total,
            });

            continue;
        }

        if (!comboGroups.has(item.combo_ref)) {
            const group = {
                type: 'combo',
                key: `combo:${item.combo_ref}`,
                comboRef: item.combo_ref,
                items: [],
                quantity: item.quantity,
                lineTotal: '0.00',
            };

            comboGroups.set(item.combo_ref, group);
            groups.push(group);
        }

        const group = comboGroups.get(item.combo_ref);
        group.items.push(item);
        group.quantity = Math.min(group.quantity, item.quantity);
        group.lineTotal = formatMoney(
            Number.parseFloat(group.lineTotal) + Number.parseFloat(item.line_total),
        );
    }

    return groups;
}

/**
 * @param {object} group
 * @returns {string}
 */
export function getCartGroupTitle(group) {
    if (group.type !== 'combo') {
        return group.item.dish_name;
    }

    return `Комбо: ${group.items.map((item) => item.dish_name).join(' / ')}`;
}

/**
 * @param {object|null} cart
 * @returns {number}
 */
export function countCartGroupsQuantity(cart) {
    return buildCartGroups(cart).reduce((sum, group) => sum + group.quantity, 0);
}

/**
 * @param {number} value
 * @returns {string}
 */
function formatMoney(value) {
    return value.toFixed(2);
}
