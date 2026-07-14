/**
 * Scope экрана корзины (CartPage).
 *
 * Единый источник решений по составу экрана. Подкомпоненты в `./` (CartHeader,
 * CartItemList и т.д.) должны соответствовать только секциям из CART_PAGE_SECTIONS.
 *
 * Чат клиент ↔ админ — только на OrderDetailPage через OrderChatPanel
 * (GET/POST /api/food/orders/{id}/messages). Cross-sell комбо — на MenuPage
 * (MenuComboBuilderSheet), не в корзине.
 *
 * @module cart/cartScope
 */

/** @typedef {'header' | 'items' | 'summary-footer' | 'confirm-modal'} CartPageSection */

/**
 * Разрешённые секции CartPage.
 * Адрес доставки редактируется в header (CartHeader), не в теле страницы.
 * @type {readonly CartPageSection[]}
 */
export const CART_PAGE_SECTIONS = Object.freeze([
    'header',
    'items',
    'summary-footer',
    'confirm-modal',
]);

/**
 * Фичи, сознательно исключённые из CartPage (не реализовывать без отдельного решения).
 * @type {readonly string[]}
 */
export const CART_PAGE_OUT_OF_SCOPE = Object.freeze([
    'upsell-carousel',
    'promotions-carousel',
    'order-chat-panel',
    'cart-messages-api',
]);
