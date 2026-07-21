/**
 * Идентификаторы экранов и ролей MAX mini-app.
 * Единый источник правды для навигации без vue-router.
 */

/** Роль проверяющего адрес доставки и оплату */
export const ROLE_ADDRESS = 'address_reviewer';

/** Роль проверяющего состав заказа */
export const ROLE_COMPOSITION = 'composition_reviewer';

/** Роль управления меню (CRUD блюд) */
export const ROLE_MENU = 'menu_manager';

/** Роль ручного оформления заказов от имени клиента */
export const ROLE_MAX_MANAGER = 'max_manager';

/** Разделы админ-интерфейса: проверка заказов, ручные заказы или меню */
export const ADMIN_SECTIONS = {
    orders: 'orders',
    manualOrders: 'manualOrders',
    menu: 'menu',
};

/** Экраны клиентского потока: ресторан → меню → корзина → заказ */
export const VIEWS = {
    restaurants: 'restaurants',
    menu: 'menu',
    cart: 'cart',
    confirmation: 'confirmation',
    orderList: 'orderList',
    orderDetail: 'orderDetail',
};

/** Экраны админ-потока: список очереди / карточка заказа */
export const ADMIN_VIEWS = {
    list: 'list',
    detail: 'detail',
};

/** Экраны CRUD блюд в разделе «Меню» */
export const ADMIN_DISH_VIEWS = {
    list: 'dishList',
    form: 'dishForm',
    schedule: 'dishSchedule',
    categoryList: 'categoryList',
    categoryForm: 'categoryForm',
};
