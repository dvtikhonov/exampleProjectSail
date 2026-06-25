/**
 * Маппинг статусов заказа и этапов проверки в подписи и CSS-классы бейджей.
 */

/**
 * @typedef {{ label: string, badgeClass: string }} OrderStatusDisplay
 */

/**
 * @param {string|undefined|null} reviewStatus
 * pending и not_applicable трактуются как «ещё не пройден этап»
 */
function isReviewStagePending(reviewStatus) {
    return reviewStatus === 'pending' || reviewStatus === 'not_applicable';
}

/**
 * Человекочитаемый статус заказа и классы бейджа для карточки.
 *
 * @param {{ status: string, address_review_status?: string, composition_review_status?: string, payment_review_status?: string }} order
 * @returns {OrderStatusDisplay}
 */
export function getOrderStatusDisplay(order) {
    const status = order.status;

    if (status === 'confirmed') {
        return {
            label: 'Выполнен',
            badgeClass: 'bg-green-100 text-green-800',
        };
    }

    if (status === 'rejected') {
        return {
            label: 'Отклонён',
            badgeClass: 'bg-red-100 text-red-800',
        };
    }

    if (status === 'awaiting_composition') {
        return {
            label: 'Ожидает состав',
            badgeClass: 'bg-amber-100 text-amber-800',
        };
    }

    const addressPending = order.address_review_status === 'pending';
    const compositionPending = isReviewStagePending(order.composition_review_status);
    const paymentPending = order.payment_review_status === 'pending';

    if (addressPending && compositionPending && paymentPending) {
        return {
            label: 'Поступил',
            badgeClass: 'bg-red-100 text-red-800',
        };
    }

    return {
        label: 'На проверке',
        badgeClass: 'bg-amber-100 text-amber-800',
    };
}

/**
 * @param {'address'|'composition'|'payment'} scope
 * @param {string} reviewStatus
 * @returns {OrderStatusDisplay}
 */
export function getReviewStageDisplay(scope, reviewStatus) {
    const scopeLabel = scope === 'address' ? 'Адрес' : scope === 'payment' ? 'Оплата' : 'Состав';

    if (reviewStatus === 'approved') {
        return {
            label: `${scopeLabel}: подтверждён`,
            badgeClass: 'bg-green-100 text-green-800',
        };
    }

    if (reviewStatus === 'rejected') {
        return {
            label: `${scopeLabel}: отклонён`,
            badgeClass: 'bg-red-100 text-red-800',
        };
    }

    return {
        label: `${scopeLabel}: поступил`,
        badgeClass: 'bg-red-100 text-red-800',
    };
}
