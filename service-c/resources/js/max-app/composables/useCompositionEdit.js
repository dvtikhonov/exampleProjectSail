/**
 * UI-слой редактирования состава: режим правки, модалки, combo sheet.
 * Доменный draft — в useCompositionDraft.
 */
import { computed, ref, watch } from 'vue';
import { useCompositionDraft } from './useCompositionDraft';

/**
 * @param {import('vue').Ref<object|null>} orderRef
 * @param {import('vue').Ref<string>} scopeRef
 * @returns {object}
 */
export function useCompositionEdit(orderRef, scopeRef) {
    const draft = useCompositionDraft(orderRef, scopeRef);

    const isEditMode = ref(false);
    const showConfirmModal = ref(false);
    const menuPickerOpen = ref(false);

    const comboBuilderOpen = ref(false);
    const comboFirstDish = ref(null);
    const comboSecondDish = ref(null);
    const comboQuantity = ref(1);

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

    function closeComboBuilder() {
        comboBuilderOpen.value = false;
        comboFirstDish.value = null;
        comboSecondDish.value = null;
        comboQuantity.value = 1;
    }

    function resetEditState() {
        isEditMode.value = false;
        showConfirmModal.value = false;
        menuPickerOpen.value = false;
        closeComboBuilder();
        draft.clearDraft();
    }

    async function startEdit() {
        if (!draft.initDraftFromOrder()) {
            return;
        }

        isEditMode.value = true;
        await draft.ensureMenuLoaded();
    }

    function cancelEdit() {
        resetEditState();
    }

    function openMenuPicker() {
        menuPickerOpen.value = true;
        closeComboBuilder();
        draft.ensureMenuLoaded();
    }

    function closeMenuPicker() {
        menuPickerOpen.value = false;
        closeComboBuilder();
    }

    function openSaveConfirm() {
        const validationError = draft.validateDraftForSave();

        if (validationError) {
            draft.saveError.value = validationError;

            return;
        }

        draft.saveError.value = '';
        showConfirmModal.value = true;
    }

    function closeSaveConfirm() {
        if (!draft.saveLoading.value) {
            showConfirmModal.value = false;
        }
    }

    /**
     * @param {object} dish
     * @param {number} [quantity]
     */
    function addDishFromMenu(dish, quantity = 1) {
        draft.addDishFromMenu(dish, quantity);
        closeMenuPicker();
    }

    /**
     * @param {{ firstDish: object, secondDish: object, quantity: number, comboRef: string }} payload
     */
    function addComboFromMenu(payload) {
        draft.addComboFromMenu(payload);
        closeMenuPicker();
    }

    /**
     * @param {object} dish
     */
    function startComboBuilder(dish) {
        if (!dish) {
            return;
        }

        comboBuilderOpen.value = true;
        comboFirstDish.value = dishWithCategory(dish);
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
        comboQuantity.value = draft.clampQuantity(comboQuantity.value + delta);
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
     * @param {(order: object) => void} onSaved
     */
    async function confirmSave(onSaved) {
        const updatedOrder = await draft.saveComposition();

        if (updatedOrder) {
            showConfirmModal.value = false;
            resetEditState();
            onSaved?.(updatedOrder);
        }
    }

    return {
        isEditMode,
        canEdit: draft.canEdit,
        draftItems: draft.draftItems,
        draftGroups: draft.draftGroups,
        draftItemsTotal: draft.draftItemsTotal,
        menu: draft.menu,
        menuLoading: draft.menuLoading,
        menuError: draft.menuError,
        saveLoading: draft.saveLoading,
        saveError: draft.saveError,
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
        updateGroupQuantity: draft.updateGroupQuantity,
        removeGroup: draft.removeGroup,
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
