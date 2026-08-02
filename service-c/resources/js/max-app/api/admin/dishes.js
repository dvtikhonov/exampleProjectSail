/**
 * Админ: CRUD блюд, импорт и DEV test-bot эндпоинты.
 *
 * @typedef {import('../types.js').AdminDishDto} AdminDishDto
 */
import { client } from '../http';

/**
 * @param {{
 *   restaurantId?: number|null,
 *   categoryId?: number|null,
 *   name?: string|null,
 *   availability?: string|null,
 * }} [filters]
 * @returns {Promise<{
 *   dishes: AdminDishDto[],
 *   menuAvailabilityDate: string|null,
 *   menuAvailabilityError: string|null,
 * }>}
 */
export async function fetchAdminDishes({
    restaurantId = null,
    categoryId = null,
    name = null,
    availability = null,
} = {}) {
    const params = {};

    if (restaurantId !== null) {
        params.restaurant_id = restaurantId;
    }

    if (categoryId !== null) {
        params.category_id = categoryId;
    }

    if (name !== null && name !== '') {
        params.name = name;
    }

    if (availability !== null && availability !== '' && availability !== 'all') {
        params.availability = availability;
    }

    const { data } = await client.get('/food/admin/dishes', { params });

    return {
        dishes: data.dishes,
        menuAvailabilityDate: data.menu_availability_date ?? null,
        menuAvailabilityError: data.menu_availability_error ?? null,
    };
}

/**
 * @param {number} dishId
 * @returns {Promise<AdminDishDto>}
 */
export async function fetchAdminDish(dishId) {
    const { data } = await client.get(`/food/admin/dishes/${dishId}`);

    return data.dish;
}

/**
 * @param {object} fields
 * @param {File} photoFile
 * @returns {Promise<AdminDishDto>}
 */
export async function createDish(fields, photoFile) {
    const formData = buildDishFormData(fields, photoFile);

    const { data } = await client.post('/food/admin/dishes', formData);

    return data.dish;
}

/**
 * @param {number} dishId
 * @param {object} fields
 * @param {File|null} [photoFile]
 * @returns {Promise<AdminDishDto>}
 */
export async function updateDish(dishId, fields, photoFile = null) {
    const formData = buildDishFormData(fields, photoFile);

    const { data } = await client.post(`/food/admin/dishes/${dishId}`, formData);

    return data.dish;
}

/**
 * @param {number} dishId
 */
export async function deleteDish(dishId) {
    await client.delete(`/food/admin/dishes/${dishId}`);
}

/**
 * @param {File} file
 * @param {number} menuCategoryId
 * @returns {Promise<{ imported_count: number, errors: object[] }>}
 */
export async function importDishesSpreadsheet(file, menuCategoryId) {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('menu_category_id', String(menuCategoryId));

    const { data } = await client.post('/food/admin/dishes/import', formData);

    return data;
}

/**
 * @returns {Promise<{ message: string, bot_username: string }>}
 */
export async function sendAdminTestBotMessage() {
    const { data } = await client.post('/food/admin/dishes/test-bot');

    return data;
}

/**
 * @returns {Promise<{ message: string, bot_username: string }>}
 */
export async function sendAdminTestBot2Message() {
    const { data } = await client.post('/food/admin/dishes/test-bot-2');

    return data;
}

/**
 * @param {object} fields
 * @param {File|null} photoFile
 * @returns {FormData}
 */
function buildDishFormData(fields, photoFile = null) {
    const formData = new FormData();

    formData.append('name', fields.name);
    formData.append('menu_category_id', String(fields.menu_category_id));

    if (fields.description) {
        formData.append('description', fields.description);
    }

    formData.append('weight', String(fields.weight));
    formData.append('weight_unit', fields.weight_unit);
    formData.append('price', String(fields.price));

    // Пустая строка → null («Не облагается НДС»); ключ обязателен для partial update.
    formData.append(
        'vat_rate',
        fields.vat_rate === null || fields.vat_rate === undefined || fields.vat_rate === ''
            ? ''
            : String(fields.vat_rate),
    );

    formData.append('is_available', fields.is_available ? '1' : '0');

    if (photoFile instanceof File) {
        formData.append('photo', photoFile);
    }

    return formData;
}
