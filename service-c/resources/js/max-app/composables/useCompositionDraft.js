/**
 * Доменный draft состава заказа: позиции, валидация, сохранение, загрузка меню.
 * UI-состояние (модалки, combo sheet) — в useCompositionEdit.
 */
import { computed, ref } from 'vue';
import {
    extractErrorMessage,
    fetchMenu,
    updateOrderComposition,
} from '../api';
import {
    COMPOSITION_MAX_QUANTITY,
    COMPOSITION_MIN_QUANTITY,
} from '../constants/composition';
import {
    buildSnapshotGroups,
    calculateSnapshotItemsTotal,
} from '../utils/orderSnapshotGroups';

/**
 * @param {import('vue').Ref<object|null>} orderRef
 * @param {import('vue').Ref<string>} scopeRef
 * @returns {object}
 */
export function useCompositionDraft(orderRef, scopeRef) {
    const draftItems = ref([]);
    const menu = ref(null);
    const menuLoading = ref(false);
    const menuError = ref('');
    const saveLoading = ref(false);
    const saveError = ref('');

    const canEdit = computed(() => {
        const order = orderRef.value;

        if (!order || scopeRef.value !== 'composition') {
            return false;
        }

        // Согласовано с FoodOrder::isInCompositionReviewQueue()
        if (order.status === 'rejected' || order.status === 'confirmed') {
            return false;
        }

        const status = order.composition_review_status;

        return status === 'pending' || status === 'not_applicable';
    });

    const draftGroups = computed(() => buildSnapshotGroups(draftItems.value));

    const draftItemsTotal = computed(() => calculateSnapshotItemsTotal(draftItems.value));

    /**
     * @param {Array<object>} snapshot
     * @returns {Array<object>}
     */
    function cloneSnapshot(snapshot) {
        return snapshot.map((item) => ({ ...item }));
    }

    function clearDraft() {
        draftItems.value = [];
        menu.value = null;
        menuLoading.value = false;
        menuError.value = '';
        saveLoading.value = false;
        saveError.value = '';
    }

    /**
     * @returns {boolean} true, если draft инициализирован из snapshot
     */
    function initDraftFromOrder() {
        const order = orderRef.value;

        if (!order?.items_snapshot || !canEdit.value) {
            return false;
        }

        draftItems.value = cloneSnapshot(order.items_snapshot);
        saveError.value = '';

        return true;
    }

    async function ensureMenuLoaded() {
        const order = orderRef.value;

        if (!order?.restaurant_id || menu.value) {
            return;
        }

        menuLoading.value = true;
        menuError.value = '';

        try {
            menu.value = await fetchMenu(order.restaurant_id);
        } catch (error) {
            menuError.value = extractErrorMessage(error);
        } finally {
            menuLoading.value = false;
        }
    }

    /**
     * @param {number} value
     * @returns {number}
     */
    function clampQuantity(value) {
        return Math.min(COMPOSITION_MAX_QUANTITY, Math.max(COMPOSITION_MIN_QUANTITY, value));
    }

    /**
     * @param {object} group
     * @param {number} quantity
     */
    function updateGroupQuantity(group, quantity) {
        const nextQuantity = clampQuantity(quantity);
        const nextItems = [...draftItems.value];

        for (const index of group.indices) {
            const item = nextItems[index];
            const unitPrice = Number.parseFloat(item.unit_price);

            nextItems[index] = {
                ...item,
                quantity: nextQuantity,
                line_total: (unitPrice * nextQuantity).toFixed(2),
            };
        }

        draftItems.value = nextItems;
    }

    /**
     * @param {object} group
     */
    function removeGroup(group) {
        const indicesToRemove = new Set(group.indices);
        draftItems.value = draftItems.value.filter((_, index) => !indicesToRemove.has(index));
    }

    /**
     * @param {object} dish
     * @param {number} [quantity]
     */
    function addDishFromMenu(dish, quantity = 1) {
        draftItems.value = [
            ...draftItems.value,
            buildSnapshotLineFromMenuDish(dish, clampQuantity(quantity)),
        ];
    }

    /**
     * @param {{ firstDish: object, secondDish: object, quantity: number, comboRef: string }} payload
     */
    function addComboFromMenu({ firstDish, secondDish, quantity, comboRef }) {
        const qty = clampQuantity(quantity);

        draftItems.value = [
            ...draftItems.value,
            buildSnapshotLineFromMenuDish(firstDish, qty, {
                comboRef,
                partnerDishId: secondDish.id,
            }),
            buildSnapshotLineFromMenuDish(secondDish, qty, {
                comboRef,
                partnerDishId: firstDish.id,
            }),
        ];
    }

    /**
     * @returns {string|null} Текст ошибки или null, если draft валиден для PUT
     */
    function validateDraftForSave() {
        if (draftItems.value.length === 0) {
            return 'Состав заказа не может быть пустым.';
        }

        for (const item of draftItems.value) {
            const partnerDishId = item.combo_partner_dish_ids?.[0] ?? null;
            const hasComboRef = Boolean(item.combo_ref);
            const hasPartner = partnerDishId !== null && partnerDishId !== undefined;

            if (hasComboRef !== hasPartner) {
                return 'Некорректные данные комбо. Удалите позицию и добавьте комбо заново.';
            }
        }

        return null;
    }

    /**
     * @returns {Array<{ dish_id: number, quantity: number, combo_ref?: string, combo_partner_dish_id?: number }>}
     */
    function draftToApiPayload() {
        return draftItems.value.map((item) => {
            const payload = {
                dish_id: item.dish_id,
                quantity: item.quantity,
            };

            const partnerDishId = item.combo_partner_dish_ids?.[0] ?? null;

            // combo_ref и combo_partner_dish_id только вместе (как в UpdateOrderCompositionRequest)
            if (item.combo_ref && partnerDishId !== null) {
                payload.combo_ref = item.combo_ref;
                payload.combo_partner_dish_id = partnerDishId;
            }

            return payload;
        });
    }

    /**
     * @returns {Promise<object|null>} обновлённый заказ или null при ошибке/отмене
     */
    async function saveComposition() {
        const order = orderRef.value;

        if (!order) {
            return null;
        }

        const validationError = validateDraftForSave();

        if (validationError) {
            saveError.value = validationError;

            return null;
        }

        saveLoading.value = true;
        saveError.value = '';

        try {
            return await updateOrderComposition(order.id, draftToApiPayload());
        } catch (error) {
            saveError.value = extractErrorMessage(error);

            return null;
        } finally {
            saveLoading.value = false;
        }
    }

    return {
        draftItems,
        draftGroups,
        draftItemsTotal,
        canEdit,
        menu,
        menuLoading,
        menuError,
        saveLoading,
        saveError,
        clearDraft,
        initDraftFromOrder,
        ensureMenuLoaded,
        clampQuantity,
        updateGroupQuantity,
        removeGroup,
        addDishFromMenu,
        addComboFromMenu,
        validateDraftForSave,
        saveComposition,
    };
}

/**
 * @param {object} dish
 * @param {number} quantity
 * @param {{ comboRef: string, partnerDishId: number }|null} [comboMeta]
 * @returns {object}
 */
function buildSnapshotLineFromMenuDish(dish, quantity, comboMeta = null) {
    const unitPrice = Number.parseFloat(dish.price);
    const line = {
        dish_id: dish.id,
        dish_name: dish.name,
        unit_price: typeof dish.price === 'string' ? dish.price : unitPrice.toFixed(2),
        quantity,
        line_total: (unitPrice * quantity).toFixed(2),
        image_url: dish.image_url ?? null,
    };

    if (comboMeta) {
        line.combo_ref = comboMeta.comboRef;
        line.combo_partner_dish_ids = [comboMeta.partnerDishId];
    }

    return line;
}
