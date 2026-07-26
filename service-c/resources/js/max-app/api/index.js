/**
 * Barrel API max-app: прежние именованные экспорты из foodClient + cartTransport.
 * Импорты: `from '../api'` или узкие модули (`./cart`, `./admin/dishes`, …).
 * JSDoc DTO: `import('./types.js').OrderDto` (см. `./types.js`).
 */

export {
    clearAuthToken,
    client,
    extractErrorMessage,
    extractValidationErrors,
    setAuthToken,
} from './http';

export { authenticate } from './auth';

export { addComboWithRollback } from './cartHelpers';

export {
    createCartTransport,
    createClientCartTransport,
    createManualCartTransport,
} from './cartTransport';

export {
    addComboToCart,
    addToCart,
    clearCart,
    fetchCart,
    fetchMenu,
    fetchRestaurants,
    removeCartItem,
    submitOrder,
    updateCartDeliveryAddress,
    updateCartItem,
} from './cart';

export {
    fetchMyOrders,
    fetchOrder,
    fetchOrderMessages,
    sendOrderMessage,
} from './orders';

export {
    addComboToManualCart,
    addToManualCart,
    clearManualCart,
    fetchManualCart,
    fetchManualOrder,
    fetchManualOrders,
    fetchManualOrderUsers,
    removeManualCartItem,
    submitManualOrder,
    updateManualCartDeliveryAddress,
    updateManualCartItem,
} from './manualOrders';

export {
    approveOrderAddress,
    approveOrderComposition,
    approveOrderPayment,
    fetchAdminOrder,
    fetchAdminOrders,
    rejectOrderAddress,
    rejectOrderComposition,
    rejectOrderPayment,
    updateOrderComposition,
} from './admin/review';

export {
    createMenuCategory,
    deleteMenuCategory,
    fetchAdminMenuCategories,
    fetchAdminMenuCategory,
    updateMenuCategory,
} from './admin/categories';

export {
    createDish,
    deleteDish,
    fetchAdminDish,
    fetchAdminDishes,
    importDishesSpreadsheet,
    sendAdminTestBot2Message,
    sendAdminTestBotMessage,
    updateDish,
} from './admin/dishes';

export {
    fetchDishAvailabilitySchedule,
    updateDishAvailabilitySchedule,
} from './admin/schedule';
