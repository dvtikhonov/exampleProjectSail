import axios from 'axios';

let authToken = sessionStorage.getItem('max_miniapp_token');

const client = axios.create({
    baseURL: '/api',
    headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
    },
});

client.interceptors.request.use((config) => {
    if (authToken) {
        config.headers.Authorization = `Bearer ${authToken}`;
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
 */
export async function authenticate(initData) {
    const { data } = await client.post('/max/auth', { init_data: initData });
    setAuthToken(data.token);

    return data;
}

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

/**
 * @param {unknown} error
 * @returns {string}
 */
export function extractErrorMessage(error) {
    if (axios.isAxiosError(error)) {
        return error.response?.data?.message ?? error.message;
    }

    if (error instanceof Error) {
        return error.message;
    }

    return 'Произошла ошибка';
}
