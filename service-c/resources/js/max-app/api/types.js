/**
 * Общие JSDoc-типы DTO API max-app (без TypeScript).
 * Импорт в pages/composables: `import('./types.js').OrderDto`.
 *
 * Деньги — string (`"1400.00"`), даты — ISO8601 string.
 * Поля сверены с app/DTO/Food и MaxMiniAppAuthService.
 */

/**
 * @typedef {'submitted'|'pending_review'|'awaiting_composition'|'confirmed'|'rejected'} OrderStatus
 */

/**
 * @typedef {'pending'|'approved'|'rejected'|'not_applicable'} ReviewStatus
 */

/**
 * @typedef {'customer'|'admin'} OrderMessageAuthorType
 */

/**
 * @typedef {'address_reviewer'|'composition_reviewer'|'menu_manager'|'max_manager'} AdminRole
 */

/**
 * @typedef {object} MaxUserName
 * @property {string|null} [first_name]
 * @property {string|null} [last_name]
 * @property {string|null} [username]
 */

/**
 * @typedef {object} OrderCustomer
 * @property {number} max_user_id
 * @property {string|null} first_name
 * @property {string|null} last_name
 * @property {string|null} username
 */

/**
 * Позиция снимка состава заказа (items_snapshot).
 *
 * @typedef {object} OrderSnapshotItem
 * @property {number} dish_id
 * @property {string} dish_name
 * @property {string|null} description
 * @property {string|null} weight
 * @property {string} weight_unit
 * @property {string} unit_price
 * @property {number} quantity
 * @property {string} line_total
 * @property {string|null} image_url
 * @property {string} [combo_ref]
 * @property {number[]} [combo_partner_dish_ids]
 */

/**
 * Элемент списка заказов клиента (GET /food/orders).
 *
 * @typedef {object} OrderListItemDto
 * @property {number} id
 * @property {OrderStatus|string} status
 * @property {number} restaurant_id
 * @property {string} restaurant_name
 * @property {string} total
 * @property {string|null} last_message_at
 * @property {number} unread_count
 * @property {string} created_at
 */

/**
 * Деталь / submit заказа клиента (GET/POST /food/orders).
 *
 * @typedef {object} OrderDto
 * @property {number} id
 * @property {OrderStatus|string} status
 * @property {number} restaurant_id
 * @property {string} restaurant_name
 * @property {string} items_total
 * @property {boolean} delivery_applicable
 * @property {string|null} delivery_cost
 * @property {string} total
 * @property {string|null} delivery_address
 * @property {OrderSnapshotItem[]} items_snapshot
 * @property {string} created_at
 */

/**
 * Элемент очереди админ-проверки (GET /food/admin/orders).
 *
 * @typedef {object} AdminOrderListItemDto
 * @property {number} id
 * @property {OrderStatus|string} status
 * @property {number} restaurant_id
 * @property {string} restaurant_name
 * @property {OrderCustomer} customer
 * @property {string|null} delivery_address
 * @property {string} items_total
 * @property {string|null} delivery_cost
 * @property {string} total
 * @property {ReviewStatus|string} address_review_status
 * @property {ReviewStatus|string} composition_review_status
 * @property {ReviewStatus|string} payment_review_status
 * @property {string|null} last_message_at
 * @property {number} unread_count
 * @property {string} created_at
 */

/**
 * Карточка заказа для проверяющего (GET /food/admin/orders/:id).
 *
 * @typedef {object} AdminOrderDetailDto
 * @property {number} id
 * @property {OrderStatus|string} status
 * @property {number} restaurant_id
 * @property {string} restaurant_name
 * @property {OrderCustomer} customer
 * @property {string|null} delivery_address
 * @property {string} items_total
 * @property {string|null} delivery_cost
 * @property {string} total
 * @property {OrderSnapshotItem[]} items_snapshot
 * @property {ReviewStatus|string} address_review_status
 * @property {number|null} address_reviewed_by
 * @property {string|null} address_reviewed_at
 * @property {string|null} address_rejection_comment
 * @property {ReviewStatus|string} composition_review_status
 * @property {number|null} composition_reviewed_by
 * @property {string|null} composition_reviewed_at
 * @property {string|null} composition_rejection_comment
 * @property {ReviewStatus|string} payment_review_status
 * @property {number|null} payment_reviewed_by
 * @property {string|null} payment_reviewed_at
 * @property {string|null} payment_rejection_comment
 * @property {string} created_at
 */

/**
 * @typedef {object} CartItemDto
 * @property {number} id
 * @property {number} dish_id
 * @property {string} dish_name
 * @property {string} unit_price
 * @property {number} quantity
 * @property {string} line_total
 * @property {string|null} image_url
 * @property {string|null} weight
 * @property {string|null} weight_unit
 * @property {string|null} weight_unit_label
 * @property {string} [combo_ref]
 * @property {number|null} [combo_partner_dish_id]
 * @property {string|null} [combo_partner_dish_name]
 */

/**
 * @typedef {object} CartCustomerCategory
 * @property {number} id
 * @property {string} name
 */

/**
 * Корзина клиента / manual (поле `cart` в ответе API).
 *
 * @typedef {object} CartDto
 * @property {number} id
 * @property {number} restaurant_id
 * @property {string} restaurant_name
 * @property {string} status
 * @property {CartItemDto[]} items
 * @property {string} items_total
 * @property {string|null} delivery_cost
 * @property {string} total
 * @property {string|null} delivery_address
 * @property {CartCustomerCategory|null} customer_category
 * @property {boolean} delivery_applicable
 * @property {string|null} next_tier_min_total
 * @property {string|null} next_tier_delivery_cost
 * @property {string|null} amount_to_next_tier
 */

/**
 * Обёртка GET/PATCH корзины: cart + адрес профиля.
 *
 * @typedef {object} CartEnvelope
 * @property {CartDto|null} cart
 * @property {string|null} deliveryAddress
 */

/**
 * @typedef {object} RestaurantDto
 * @property {number} id
 * @property {string} name
 * @property {string} address
 */

/**
 * Блюдо в клиентском меню.
 *
 * @typedef {object} MenuDishDto
 * @property {number} id
 * @property {string} name
 * @property {string} price
 * @property {boolean} is_available
 * @property {string|null} image_url
 */

/**
 * @typedef {object} MenuCategoryDto
 * @property {number} id
 * @property {string} name
 * @property {boolean} is_combo_available
 * @property {MenuDishDto[]} dishes
 */

/**
 * @typedef {object} MenuDto
 * @property {number} restaurant_id
 * @property {string} restaurant_name
 * @property {MenuCategoryDto[]} categories
 */

/**
 * @typedef {object} OrderMessageDto
 * @property {number} id
 * @property {number} food_order_id
 * @property {number} sender_max_user_id
 * @property {MaxUserName} sender
 * @property {OrderMessageAuthorType|string} author_type
 * @property {string} body
 * @property {string} created_at
 */

/**
 * Пользователь после POST /max/auth.
 *
 * @typedef {object} AuthUserDto
 * @property {number} max_user_id
 * @property {string} first_name
 * @property {string|null} last_name
 * @property {string|null} username
 * @property {string|null} language_code
 * @property {string|null} photo_url
 * @property {AdminRole[]|string[]} admin_roles
 */

/**
 * @typedef {object} AuthResponseDto
 * @property {string} token
 * @property {string} token_type
 * @property {number} expires_in
 * @property {AuthUserDto} user
 */

/**
 * Клиент для ручного заказа (GET …/manual-orders/users).
 *
 * @typedef {object} ManualOrderUserDto
 * @property {number} max_user_id
 * @property {string|null} first_name
 * @property {string|null} last_name
 * @property {string|null} username
 * @property {string|null} delivery_address
 */

/**
 * @typedef {object} AdminDishDto
 * @property {number} id
 * @property {string} name
 * @property {string|null} description
 * @property {number} menu_category_id
 * @property {string} menu_category_name
 * @property {number} restaurant_id
 * @property {string} restaurant_name
 * @property {string} weight
 * @property {string} weight_unit
 * @property {string} weight_unit_label
 * @property {string} price
 * @property {number|null} vat_rate
 * @property {string} vat_rate_label
 * @property {boolean} is_available
 * @property {string|null} image_url
 */

/**
 * Правило смещения доступности блюд категории (админка).
 * Дни недели — ISO: 1=Пн … 7=Вс.
 *
 * @typedef {object} MenuCategoryAvailabilityOffsetDto
 * @property {number[]} weekdays
 * @property {number} offset_days
 */

/**
 * @typedef {object} AdminMenuCategoryDto
 * @property {number} id
 * @property {string} name
 * @property {number} restaurant_id
 * @property {string} restaurant_name
 * @property {number} sort_order
 * @property {boolean} is_combo_available
 * @property {number} dishes_count
 * @property {MenuCategoryAvailabilityOffsetDto[]} availability_offsets
 */

/**
 * Позиция для PUT composition.
 *
 * @typedef {object} CompositionUpdateItem
 * @property {number} dish_id
 * @property {number} quantity
 * @property {string} [combo_ref]
 * @property {number} [combo_partner_dish_id]
 */

export {};
