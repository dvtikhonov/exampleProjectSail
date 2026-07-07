/**
 * Пометки комбо в items_snapshot заказа: partner name по combo_ref и combo_partner_dish_ids.
 */

/**
 * @param {object} item
 * @returns {boolean}
 */
export function isComboSnapshotItem(item) {
    return Boolean(item?.combo_ref);
}

/**
 * Имя партнёра по combo_partner_dish_ids или соседней строке с тем же combo_ref.
 *
 * @param {object} item
 * @param {Array<object>} itemsSnapshot
 * @returns {string|null}
 */
export function getComboPartnerName(item, itemsSnapshot) {
    if (!isComboSnapshotItem(item) || !Array.isArray(itemsSnapshot)) {
        return null;
    }

    const partnerIds = item.combo_partner_dish_ids ?? [];

    for (const partnerId of partnerIds) {
        const partnerInSameCombo = itemsSnapshot.find(
            (other) => other.combo_ref === item.combo_ref && other.dish_id === partnerId,
        );

        if (partnerInSameCombo?.dish_name) {
            return partnerInSameCombo.dish_name;
        }

        const anyWithDishId = itemsSnapshot.find((other) => other.dish_id === partnerId);

        if (anyWithDishId?.dish_name) {
            return anyWithDishId.dish_name;
        }
    }

    const sibling = itemsSnapshot.find(
        (other) => other.combo_ref === item.combo_ref && other.dish_id !== item.dish_id,
    );

    return sibling?.dish_name ?? null;
}
