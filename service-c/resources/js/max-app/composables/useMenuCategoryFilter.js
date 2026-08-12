import { computed, ref, unref } from 'vue';

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

    const categoryTabs = computed(() => [
        { id: null, name: 'Все' },
        ...visibleCategories.value.map((category) => ({
            id: category.id,
            name: category.name,
        })),
    ]);

    const filteredDishes = computed(() => {
        let dishes = visibleCategories.value.flatMap((category) =>
            category.dishes.map((dish) => ({
                ...dish,
                category_id: category.id,
                category_name: category.name,
                is_combo_available: category.is_combo_available !== false,
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
        searchQuery,
        categoryTabs,
        filteredDishes,
    };
}
