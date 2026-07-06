/**
 * Общие фильтры админ-потока «Меню»: список блюд и график доступности.
 */
import { ref } from 'vue';

/**
 * @returns {{
 *   filterRestaurantId: import('vue').Ref<string>,
 *   filterCategoryId: import('vue').Ref<string>,
 *   filterNameSearch: import('vue').Ref<string>,
 * }}
 */
export function useDishAdminFilters() {
    const filterRestaurantId = ref('');
    const filterCategoryId = ref('');
    const filterNameSearch = ref('');

    return {
        filterRestaurantId,
        filterCategoryId,
        filterNameSearch,
    };
}
