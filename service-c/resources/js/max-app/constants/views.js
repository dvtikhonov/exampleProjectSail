/**
 * Идентификаторы экранов и ролей MAX mini-app.
 * Единый источник правды для навигации без vue-router.
 *
 * Глоссарий админ-навигации (не путать):
 *
 * - **adminScope** — вкладка очереди проверки заказов: `address` | `composition`
 *   (роли address_reviewer / composition_reviewer). Задаётся в useAuth / useAdminFlow
 *   и уходит в API как query `scope` при загрузке админ-заказов.
 *   См. {@link ADMIN_SCOPES}.
 *
 * - **adminSection** — верхний раздел AdminAppShell: заказы / ручные заказы / меню
 *   (`orders` | `manualOrders` | `menu`). Переключается в AdminSectionNav.
 *   См. {@link ADMIN_SECTIONS}.
 *
 * Scope ≠ section: у пользователя с ролями address+menu одновременно могут быть
 * adminSection=menu и (неиспользуемый до возврата в orders) adminScope=address.
 */

/** Роль проверяющего адрес доставки и оплату */
export const ROLE_ADDRESS = 'address_reviewer';

/** Роль проверяющего состав заказа */
export const ROLE_COMPOSITION = 'composition_reviewer';

/** Роль управления меню (CRUD блюд) */
export const ROLE_MENU = 'menu_manager';

/** Роль ручного оформления заказов от имени клиента */
export const ROLE_MAX_MANAGER = 'max_manager';

/**
 * Вкладки очереди проверки (adminScope).
 * Не путать с {@link ADMIN_SECTIONS} (adminSection).
 */
export const ADMIN_SCOPES = {
    address: 'address',
    composition: 'composition',
};

/**
 * Разделы админ-интерфейса (adminSection): проверка заказов, ручные заказы или меню.
 * Не путать с {@link ADMIN_SCOPES} (adminScope).
 */
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
