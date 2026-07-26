/**
 * Общие хелперы корзины без привязки к HTTP-клиенту.
 */

/**
 * @typedef {{ comboRef?: string|null, comboPartnerDishId?: number|null }} CartAddItemOptions
 */

/**
 * Добавление комбо с откатом первой позиции при ошибке второй.
 *
 * @param {object} deps
 * @param {(dishId: number, quantity: number, options?: CartAddItemOptions) => Promise<object>} deps.addItem
 * @param {(itemId: number) => Promise<object|null>} deps.removeItem
 * @param {number} deps.firstDishId
 * @param {number} deps.secondDishId
 * @param {number} deps.quantity
 * @param {string} deps.comboRef
 * @returns {Promise<object>}
 */
export async function addComboWithRollback({
    addItem,
    removeItem,
    firstDishId,
    secondDishId,
    quantity,
    comboRef,
}) {
    let firstCart = null;

    try {
        firstCart = await addItem(firstDishId, quantity, {
            comboRef,
            comboPartnerDishId: secondDishId,
        });

        return await addItem(secondDishId, quantity, {
            comboRef,
            comboPartnerDishId: firstDishId,
        });
    } catch (error) {
        const firstComboItem = firstCart?.items?.find(
            (item) => item.combo_ref === comboRef && item.dish_id === firstDishId,
        );

        if (firstComboItem) {
            await removeItem(firstComboItem.id).catch(() => {});
        }

        throw error;
    }
}
