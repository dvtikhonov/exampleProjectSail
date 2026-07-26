/**
 * Админ: CRUD категорий меню.
 *
 * @typedef {import('../types.js').AdminMenuCategoryDto} AdminMenuCategoryDto
 */
import { client } from '../http';

/**
 * @param {number|null} [restaurantId]
 * @returns {Promise<AdminMenuCategoryDto[]>}
 */
export async function fetchAdminMenuCategories(restaurantId = null) {
    const params = {};

    if (restaurantId !== null) {
        params.restaurant_id = restaurantId;
    }

    const { data } = await client.get('/food/admin/menu-categories', { params });

    return data.categories;
}

/**
 * @param {number} categoryId
 * @returns {Promise<AdminMenuCategoryDto>}
 */
export async function fetchAdminMenuCategory(categoryId) {
    const { data } = await client.get(`/food/admin/menu-categories/${categoryId}`);

    return data.category;
}

/**
 * @param {object} fields
 * @returns {Promise<AdminMenuCategoryDto>}
 */
export async function createMenuCategory(fields) {
    const { data } = await client.post('/food/admin/menu-categories', fields);

    return data.category;
}

/**
 * @param {number} categoryId
 * @param {object} fields
 * @returns {Promise<AdminMenuCategoryDto>}
 */
export async function updateMenuCategory(categoryId, fields) {
    const { data } = await client.put(`/food/admin/menu-categories/${categoryId}`, fields);

    return data.category;
}

/**
 * @param {number} categoryId
 */
export async function deleteMenuCategory(categoryId) {
    await client.delete(`/food/admin/menu-categories/${categoryId}`);
}
