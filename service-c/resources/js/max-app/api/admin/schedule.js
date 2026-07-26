/**
 * Админ: график доступности блюд.
 *
 * @typedef {import('../types.js').AdminDishDto} AdminDishDto
 */
import { client } from '../http';

/**
 * @param {{ restaurantId: number, categoryId: number, dateFrom?: string|null, dateTo?: string|null }} params
 * @returns {Promise<{ dishes: AdminDishDto[], dates: string[], schedule: Record<string, string[]>, editable_from: string }>}
 */
export async function fetchDishAvailabilitySchedule({
    restaurantId,
    categoryId,
    dateFrom = null,
    dateTo = null,
}) {
    const params = {
        restaurant_id: restaurantId,
        category_id: categoryId,
    };

    if (dateFrom !== null) {
        params.date_from = dateFrom;
    }

    if (dateTo !== null) {
        params.date_to = dateTo;
    }

    const { data } = await client.get('/food/admin/dish-availability-schedule', { params });

    return data;
}

/**
 * @param {{ restaurantId: number, categoryId: number, changes: { dish_id: number, dates: string[] }[] }} params
 * @returns {Promise<void>}
 */
export async function updateDishAvailabilitySchedule({ restaurantId, categoryId, changes }) {
    await client.put('/food/admin/dish-availability-schedule', {
        restaurant_id: restaurantId,
        category_id: categoryId,
        changes,
    });
}
