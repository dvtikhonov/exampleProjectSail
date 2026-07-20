/**
 * Редактирование состава заказа в режиме composition review: draft, меню, сохранение.
 */
import { computed, ref, watch } from 'vue';
import {
    extractErrorMessage,
    fetchMenu,
    updateOrderComposition,
} from '../api/foodClient';
import {
    buildSnapshotGroups,
    calculateSnapshotItemsTotal,
} from '../utils/orderSnapshotGroups';

const MIN_QUANTITY = 1;
const MAX_QUANTITY = 99;

/**
 * @param {import('vue').Ref<object|null>} orderRef
 * @param {import('vue').Ref<string>} scopeRef
 * @returns {object}
 */
export function useCompositionEdit(orderRef, scopeRef) {
    const isEditMode = ref(false);
    const draftItems = ref([]);
    const menu = ref(null);
    const menuLoading = ref(false);
    const menuError = ref('');
    const saveLoading = ref(false);
    const saveError = ref('');
    const showConfirmModal = ref(false);
    const menuPickerOpen = ref(false);

    const comboBuilderOpen = ref(false);
    const comboFirstDish = ref(null);
    const comboSecondDish = ref(null);
    const comboQuantity = ref(1);

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

    const comboTotal = computed(() => {
        if (!comboFirstDish.value || !comboSecondDish.value) {
            return '0.00';
        }

        const firstPrice = Number.parseFloat(comboFirstDish.value.price);
        const secondPrice = Number.parseFloat(comboSecondDish.value.price);
        const total = (firstPrice + secondPrice) * comboQuantity.value;

        return total.toFixed(2);
    });

    const canAddCombo = computed(
        () => comboFirstDish.value !== null && comboSecondDish.value !== null,
    );

    watch(
        () => orderRef.value?.id,
        () => {
            resetEditState();
        },
    );

    function resetEditState() {
        isEditMode.value = false;
        draftItems.value = [];
        menu.value = null;
        menuLoading.value = false;
        menuError.value = '';
        saveLoading.value = false;
        saveError.value = '';
        showConfirmModal.value = false;
        menuPickerOpen.value = false;
        closeComboBuilder();
    }

    /**
     * @param {Array<object>} snapshot
     * @returns {Array<object>}
     */
    function cloneSnapshot(snapshot) {
        return snapshot.map((item) => ({ ...item }));
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

    async function startEdit() {
        const order = orderRef.value;

        if (!order?.items_snapshot || !canEdit.value) {
            return;
        }

        draftItems.value = cloneSnapshot(order.items_snapshot);
        saveError.value = '';
        isEditMode.value = true;
        await ensureMenuLoaded();
    }

    function cancelEdit() {
        resetEditState();
    }

    function openMenuPicker() {
        menuPickerOpen.value = true;
        closeComboBuilder();
        ensureMenuLoaded();
    }

    function closeMenuPicker() {
        menuPickerOpen.value = false;
        closeComboBuilder();
    }

    function openSaveConfirm() {
        const validationError = validateDraftForSave();

        if (validationError) {
            saveError.value = validationError;

            return;
        }

        saveError.value = '';
        showConfirmModal.value = true;
    }

    function closeSaveConfirm() {
        if (!saveLoading.value) {
            showConfirmModal.value = false;
        }
    }

    /**
     * @param {number} value
     * @returns {number}
     */
    function clampQuantity(value) {
        return Math.min(MAX_QUANTITY, Math.max(MIN_QUANTITY, value));
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
        closeMenuPicker();
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
        closeMenuPicker();
    }

    function startComboBuilder(dish) {
        if (!dish) {
            return;
        }

        comboBuilderOpen.value = true;
        comboFirstDish.value = dishWithCategory(dish);
        comboSecondDish.value = null;
        comboQuantity.value = 1;
    }

    function closeComboBuilder() {
        comboBuilderOpen.value = false;
        comboFirstDish.value = null;
        comboSecondDish.value = null;
        comboQuantity.value = 1;
    }

    function resetSecondComboDish() {
        comboSecondDish.value = null;
        comboQuantity.value = 1;
    }

    /**
     * @param {object} dish
     */
    function selectSecondComboDish(dish) {
        const selectedDish = dishWithCategory(dish);

        if (comboSecondDish.value?.id === selectedDish.id) {
            comboSecondDish.value = null;

            return;
        }

        comboSecondDish.value = selectedDish;
    }

    /**
     * @param {number} delta
     */
    function changeComboQuantity(delta) {
        comboQuantity.value = clampQuantity(comboQuantity.value + delta);
    }

    function handleAddCombo() {
        if (!canAddCombo.value) {
            return;
        }

        addComboFromMenu({
            firstDish: comboFirstDish.value,
            secondDish: comboSecondDish.value,
            quantity: comboQuantity.value,
            comboRef: generateComboRef(),
        });
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
     * @param {(order: object) => void} onSaved
     */
    async function confirmSave(onSaved) {
        const order = orderRef.value;

        if (!order) {
            return;
        }

        const validationError = validateDraftForSave();

        if (validationError) {
            saveError.value = validationError;

            return;
        }

        saveLoading.value = true;
        saveError.value = '';

        try {
            const updatedOrder = await updateOrderComposition(order.id, draftToApiPayload());
            showConfirmModal.value = false;
            resetEditState();
            onSaved?.(updatedOrder);
        } catch (error) {
            saveError.value = extractErrorMessage(error);
        } finally {
            saveLoading.value = false;
        }
    }

    return {
        isEditMode,
        canEdit,
        draftItems,
        draftGroups,
        draftItemsTotal,
        menu,
        menuLoading,
        menuError,
        saveLoading,
        saveError,
        showConfirmModal,
        menuPickerOpen,
        comboBuilderOpen,
        comboFirstDish,
        comboSecondDish,
        comboQuantity,
        comboTotal,
        canAddCombo,
        startEdit,
        cancelEdit,
        openMenuPicker,
        closeMenuPicker,
        openSaveConfirm,
        closeSaveConfirm,
        updateGroupQuantity,
        removeGroup,
        addDishFromMenu,
        startComboBuilder,
        closeComboBuilder,
        resetSecondComboDish,
        selectSecondComboDish,
        changeComboQuantity,
        handleAddCombo,
        confirmSave,
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

/**
 * @param {object} dish
 * @returns {object}
 */
function dishWithCategory(dish) {
    return {
        ...dish,
        category_id: dish.category_id,
        category_name: dish.category_name,
    };
}

/**
 * @returns {string}
 */
function generateComboRef() {
    if (window.crypto?.randomUUID) {
        return window.crypto.randomUUID();
    }

    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
        const random = Math.trunc(Math.random() * 16);
        const value = char === 'x' ? random : (random & 0x3) | 0x8;

        return value.toString(16);
    });
}
