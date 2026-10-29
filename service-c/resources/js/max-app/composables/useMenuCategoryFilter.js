import { computed, ref, unref, watch } from 'vue';

/**
 * Клиентская фильтрация меню по категории и поисковому запросу.
 *
 * @param {import('vue').MaybeRefOrGetter<Object|null>} menu
 * @param {{
 *   comboBuilderOpen?: import('vue').Ref<boolean>,
 *   comboFirstDish?: import('vue').Ref<Object|null>,
 *   includeUnavailable?: import('vue').MaybeRefOrGetter<boolean>,
 * }} [options]
 */
export function useMenuCategoryFilter(menu, options = {}) {
    const activeCategoryId = ref(null);
    const searchQuery = ref('');

    const availableCategories = computed(() => {
        const menuValue = unref(menu);
        const includeUnavailable = Boolean(unref(options.includeUnavailable));

        if (!menuValue?.categories) {
            return [];
        }

        return menuValue.categories
            .map((category) => ({
                ...category,
                dishes: includeUnavailable
                    ? category.dishes
                    : category.dishes.filter((dish) => dish.is_available),
            }))
            .filter((category) => category.dishes.length > 0);
    });

    const visibleCategories = computed(() => {
        let categories = availableCategories.value;

        if (options.comboBuilderOpen?.value && options.comboFirstDish?.value) {
            // В режиме «Собрать блюдо» — только другие категории с поддержкой комбо.
            categories = categories.filter(
                (category) => category.id !== options.comboFirstDish.value.category_id
                    && category.is_combo_available !== false,
            );
        }

        return categories;
    });

    const comboBuilderActive = computed(
        () => Boolean(options.comboBuilderOpen?.value && options.comboFirstDish?.value),
    );

    const categoryTabs = computed(() => {
        const tabs = visibleCategories.value.map((category) => ({
            id: category.id,
            name: category.name,
        }));

        if (comboBuilderActive.value) {
            return tabs;
        }

        return [{ id: null, name: 'Все' }, ...tabs];
    });

    const activeCategoryName = computed(() => {
        const tab = categoryTabs.value.find((item) => item.id === activeCategoryId.value);

        return tab?.name ?? '';
    });

    const categoryIdBeforeCombo = ref(null);
    const hasCategoryIdBeforeCombo = ref(false);

    watch(comboBuilderActive, (comboOpen) => {
        if (comboOpen) {
            if (!hasCategoryIdBeforeCombo.value) {
                categoryIdBeforeCombo.value = activeCategoryId.value;
                hasCategoryIdBeforeCombo.value = true;
            }

            const visibleIds = visibleCategories.value.map((category) => category.id);

            if (!visibleIds.includes(activeCategoryId.value) && visibleIds.length > 0) {
                activeCategoryId.value = visibleIds[0];
            }

            return;
        }

        if (!hasCategoryIdBeforeCombo.value) {
            return;
        }

        activeCategoryId.value = categoryIdBeforeCombo.value;
        hasCategoryIdBeforeCombo.value = false;
    });

    const comboCompanionCategoryNamesByCategoryId = computed(() => {
        const comboCategories = availableCategories.value.filter(
            (category) => category.is_combo_available !== false,
        );
        /** @type {Map<number, string[]>} */
        const namesByCategoryId = new Map();

        for (const category of comboCategories) {
            const names = comboCategories
                .filter((other) => other.id !== category.id)
                .map((other) => other.name);

            namesByCategoryId.set(category.id, names);
        }

        return namesByCategoryId;
    });

    const filteredDishes = computed(() => {
        const companionCategoryNames = comboCompanionCategoryNamesByCategoryId.value;

        let dishes = visibleCategories.value.flatMap((category) =>
            category.dishes.map((dish) => ({
                ...dish,
                category_id: category.id,
                category_name: category.name,
                is_combo_available: category.is_combo_available !== false,
                combo_companion_category_names: companionCategoryNames.get(category.id) ?? [],
            })),
        );

        const activeId = activeCategoryId.value;
        const activeStillVisible = activeId === null
            || visibleCategories.value.some((category) => category.id === activeId);

        if (activeId !== null && activeStillVisible) {
            dishes = dishes.filter((dish) => dish.category_id === activeId);
        }

        const query = searchQuery.value.trim().toLowerCase();

        if (query) {
            dishes = dishes.filter((dish) => dish.name.toLowerCase().includes(query));
        }

        return dishes;
    });

    return {
        activeCategoryId,
        activeCategoryName,
        searchQuery,
        categoryTabs,
        filteredDishes,
    };
}
