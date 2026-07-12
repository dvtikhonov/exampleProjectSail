<script setup>
/**
 * Шапка меню: адрес доставки, название ресторана, кнопка заказов.
 */
import MyOrdersButton from '../MyOrdersButton.vue';

defineProps({
    deliveryAddress: {
        type: String,
        default: '',
    },
    restaurantName: {
        type: String,
        default: '',
    },
    ordersUnreadCount: {
        type: Number,
        default: 0,
    },
});

defineEmits(['open-cart', 'open-orders']);

const addressPlaceholder = 'Укажите адрес доставки';
</script>

<template>
    <header class="sticky top-0 z-10 safe-area-top">
        <div class="bg-max-surface px-4 pb-3 pt-3">
            <div class="flex items-start justify-between gap-3">
                <button
                    type="button"
                    class="min-w-0 flex-1 text-left"
                    @click="$emit('open-cart')"
                >
                    <div class="flex items-center gap-2">
                        <span
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white text-max-primary"
                            aria-hidden="true"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 21s7-4.5 7-10a7 7 0 1 0-14 0c0 5.5 7 10 7 10Z"
                                />
                                <circle cx="12" cy="11" r="2.5" />
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate text-sm font-medium text-max-text"
                                :class="!deliveryAddress && 'text-max-muted'"
                            >
                                {{ deliveryAddress || addressPlaceholder }}
                            </p>
                            <p v-if="restaurantName" class="truncate text-xs text-max-muted">
                                {{ restaurantName }}
                            </p>
                        </div>
                        <svg
                            class="h-4 w-4 shrink-0 text-max-muted"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            aria-hidden="true"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />
                        </svg>
                    </div>
                </button>
                <MyOrdersButton
                    label="Заказы"
                    :unread-count="ordersUnreadCount"
                    button-class="rounded-full bg-white px-3 py-1.5 text-sm font-medium text-max-primary transition hover:bg-white/80"
                    @click="$emit('open-orders')"
                />
            </div>
        </div>
        <div class="h-3 rounded-t-[1.5rem] bg-white" aria-hidden="true" />
    </header>
</template>
