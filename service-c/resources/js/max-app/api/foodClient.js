/**
 * HTTP-клиент для REST API заказов еды (/api/food/*, /api/max/auth).
 * Хранит Bearer-токен в sessionStorage между перезагрузками вкладки.
 */
import axios from 'axios';

/** @type {string|null} Токен авторизации после POST /max/auth */
let authToken = sessionStorage.getItem('max_miniapp_token');

const client = axios.create({
    baseURL: '/api',
    headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
    },
});

client.interceptors.request.use((config) => {
    // Подставляем Bearer после authenticate(); без токена — только публичные эндпоинты
    if (authToken) {
        config.headers.Authorization = `Bearer ${authToken}`;
    }

    if (config.data instanceof FormData) {
        // Дефолт application/json ломает multipart: Laravel не видит поле photo
        delete config.headers['Content-Type'];
    }

    return config;
});

/**
 * @param {string} token
 */
export function setAuthToken(token) {
    authToken = token;
    sessionStorage.setItem('max_miniapp_token', token);
}

export function clearAuthToken() {
    authToken = null;
    sessionStorage.removeItem('max_miniapp_token');
}

/**
 * @param {string} initData
 * Подпись initData от MAX Bridge → JWT в sessionStorage.
 */
export async function authenticate(initData) {
    const { data } = await client.post('/max/auth', { init_data: initData });
    setAuthToken(data.token);

    return data;
}

// --- Каталог и корзина (клиент) ---

export async function fetchRestaurants() {
    const { data } = await client.get('/food/restaurants');

    return data.restaurants;
}

/**
 * @param {number} restaurantId
 */
export async function fetchMenu(restaurantId) {
    const { data } = await client.get(`/food/restaurants/${restaurantId}/menu`);

    return data.menu;
}

export async function fetchCart() {
    const { data } = await client.get('/food/cart');

    return data.cart;
}

/**
 * @param {number} dishId
 * @param {number} quantity
 */
export async function addToCart(dishId, quantity = 1) {
    const { data } = await client.post('/food/cart/items', {
        dish_id: dishId,
        quantity,
    });

    return data.cart;
}

/**
 * @param {number} itemId
 * @param {number} quantity
 */
export async function updateCartItem(itemId, quantity) {
    const { data } = await client.patch(`/food/cart/items/${itemId}`, { quantity });

    return data.cart;
}

/**
 * @param {number} itemId
 */
export async function removeCartItem(itemId) {
    const { data } = await client.delete(`/food/cart/items/${itemId}`);

    return data.cart;
}

export async function clearCart() {
    const { data } = await client.delete('/food/cart');

    return data.cart;
}

/**
 * @param {string} address
 */
export async function updateCartDeliveryAddress(address) {
    const { data } = await client.patch('/food/cart', {
        delivery_address: address,
    });

    return data.cart;
}

export async function submitOrder() {
    const { data } = await client.post('/food/orders/submit');

    return data.order;
}

// --- Заказы клиента и чат ---

/**
 * @returns {Promise<object[]>}
 */
export async function fetchMyOrders() {
    const { data } = await client.get('/food/orders');

    return data.orders;
}

/**
 * @param {number} orderId
 */
export async function fetchOrder(orderId) {
    const { data } = await client.get(`/food/orders/${orderId}`);

    return data.order;
}

/**
 * @param {number} orderId
 * @param {{ afterId?: number|null, limit?: number }} [options]
 * @returns {Promise<object[]>}
 */
export async function fetchOrderMessages(orderId, { afterId = null, limit = 50 } = {}) {
    const params = { limit };

    if (afterId !== null) {
        params.after_id = afterId;
    }

    const { data } = await client.get(`/food/orders/${orderId}/messages`, { params });

    return data.messages;
}

/**
 * @param {number} orderId
 * @param {string} body
 */
export async function sendOrderMessage(orderId, body) {
    const { data } = await client.post(`/food/orders/${orderId}/messages`, { body });

    return data.message;
}

// --- Админ: проверка адреса и состава ---

/**
 * @returns {Promise<string[]>}
 */
export async function fetchAdminMe() {
    const { data } = await client.get('/food/admin/me');

    return data.admin_roles;
}

/**
 * @param {'address'|'composition'} scope
 * @param {string} [status]
 * @returns {Promise<object[]>}
 */
export async function fetchAdminOrders(scope, status = 'pending') {
    const { data } = await client.get('/food/admin/orders', {
        params: { scope, status },
    });

    return data.orders;
}

/**
 * @param {number} orderId
 * @param {'address'|'composition'} scope
 */
export async function fetchAdminOrder(orderId, scope) {
    const { data } = await client.get(`/food/admin/orders/${orderId}`, {
        params: { scope },
    });

    return data.order;
}

/**
 * @param {number} orderId
 */
export async function approveOrderAddress(orderId) {
    const { data } = await client.post(`/food/admin/orders/${orderId}/address/approve`);

    return data.order;
}

/**
 * @param {number} orderId
 * @param {string} comment
 */
export async function rejectOrderAddress(orderId, comment) {
    const { data } = await client.post(`/food/admin/orders/${orderId}/address/reject`, {
        comment,
    });

    return data.order;
}

/**
 * @param {number} orderId
 */
export async function approveOrderPayment(orderId) {
    const { data } = await client.post(`/food/admin/orders/${orderId}/payment/approve`);

    return data.order;
}

/**
 * @param {number} orderId
 * @param {string} comment
 */
export async function rejectOrderPayment(orderId, comment) {
    const { data } = await client.post(`/food/admin/orders/${orderId}/payment/reject`, {
        comment,
    });

    return data.order;
}

/**
 * @param {number} orderId
 */
export async function approveOrderComposition(orderId) {
    const { data } = await client.post(`/food/admin/orders/${orderId}/composition/approve`);

    return data.order;
}

/**
 * @param {number} orderId
 * @param {string} comment
 */
export async function rejectOrderComposition(orderId, comment) {
    const { data } = await client.post(`/food/admin/orders/${orderId}/composition/reject`, {
        comment,
    });

    return data.order;
}

// --- Админ: управление меню (CRUD блюд) ---

/**
 * @param {number|null} [restaurantId]
 * @returns {Promise<object[]>}
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
 * @param {{ restaurantId?: number|null, categoryId?: number|null, name?: string|null }} [filters]
 * @returns {Promise<object[]>}
 */
export async function fetchAdminDishes({ restaurantId = null, categoryId = null, name = null } = {}) {
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

    const { data } = await client.get('/food/admin/dishes', { params });

    return data.dishes;
}

/**
 * @param {number} dishId
 * @returns {Promise<object>}
 */
export async function fetchAdminDish(dishId) {
    const { data } = await client.get(`/food/admin/dishes/${dishId}`);

    return data.dish;
}

/**
 * @param {object} fields
 * @param {File} photoFile
 * @returns {Promise<object>}
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
 * @returns {Promise<object>}
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

    if (fields.vat_rate !== null && fields.vat_rate !== undefined && fields.vat_rate !== '') {
        formData.append('vat_rate', String(fields.vat_rate));
    }

    formData.append('is_available', fields.is_available ? '1' : '0');

    if (photoFile instanceof File) {
        formData.append('photo', photoFile);
    }

    return formData;
}

// --- Обработка ошибок API ---

/**
 * @param {unknown} error
 * @returns {string}
 */
export function extractErrorMessage(error) {
    if (axios.isAxiosError(error)) {
        const validationMessage = extractFirstValidationError(error);

        if (validationMessage) {
            return validationMessage;
        }

        return error.response?.data?.message ?? error.message;
    }

    if (error instanceof Error) {
        return error.message;
    }

    return 'Произошла ошибка';
}

/**
 * @param {import('axios').AxiosError} error
 * @returns {Record<string, string>}
 */
export function extractValidationErrors(error) {
    if (!axios.isAxiosError(error) || error.response?.status !== 422) {
        return {};
    }

    const errors = error.response.data?.errors;

    if (!errors || typeof errors !== 'object') {
        return {};
    }

    /** @type {Record<string, string>} */
    const result = {};

    for (const [field, messages] of Object.entries(errors)) {
        if (Array.isArray(messages) && messages.length > 0 && typeof messages[0] === 'string') {
            result[field] = messages[0];
        }
    }

    return result;
}

/**
 * @param {import('axios').AxiosError} error
 * @returns {string|null}
 */
function extractFirstValidationError(error) {
    const validationErrors = extractValidationErrors(error);
    const firstMessage = Object.values(validationErrors)[0];

    return firstMessage ?? null;
}
