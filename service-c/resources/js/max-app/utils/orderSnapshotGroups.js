/**
 * Группировка items_snapshot заказа для UI редактирования: обычные блюда и комбо по combo_ref.
 */

/**
 * @param {Array<object>} itemsSnapshot
 * @returns {Array<object>}
 */
export function buildSnapshotGroups(itemsSnapshot) {
    if (!Array.isArray(itemsSnapshot) || itemsSnapshot.length === 0) {
        return [];
    }

    const groups = [];
    const comboGroups = new Map();

    itemsSnapshot.forEach((item, index) => {
        if (!item.combo_ref) {
            groups.push({
                type: 'item',
                key: `item:${index}:${item.dish_id}`,
                indices: [index],
                item,
                items: [item],
                quantity: item.quantity,
                lineTotal: item.line_total,
            });

            return;
        }

        if (!comboGroups.has(item.combo_ref)) {
            const group = {
                type: 'combo',
                key: `combo:${item.combo_ref}`,
                comboRef: item.combo_ref,
                indices: [],
                items: [],
                quantity: item.quantity,
                lineTotal: '0.00',
            };

            comboGroups.set(item.combo_ref, group);
            groups.push(group);
        }

        const group = comboGroups.get(item.combo_ref);
        group.indices.push(index);
        group.items.push(item);
        group.quantity = Math.min(group.quantity, item.quantity);
        group.lineTotal = formatMoney(
            Number.parseFloat(group.lineTotal) + Number.parseFloat(item.line_total),
        );
    });

    return groups;
}

/**
 * @param {object} group
 * @returns {string}
 */
export function getSnapshotGroupTitle(group) {
    if (group.type !== 'combo') {
        return group.item.dish_name;
    }

    return `Комбо: ${group.items.map((item) => item.dish_name).join(' / ')}`;
}

/**
 * @param {Array<object>} itemsSnapshot
 * @returns {string}
 */
export function calculateSnapshotItemsTotal(itemsSnapshot) {
    if (!Array.isArray(itemsSnapshot)) {
        return '0.00';
    }

    const total = itemsSnapshot.reduce(
        (sum, item) => sum + Number.parseFloat(item.line_total ?? 0),
        0,
    );

    return formatMoney(total);
}

/**
 * @param {number} value
 * @returns {string}
 */
function formatMoney(value) {
    return value.toFixed(2);
}
