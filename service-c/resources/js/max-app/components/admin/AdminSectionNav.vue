<script setup>
/**
 * Переключатель разделов админки: заказы / ручные заказы / меню.
 */
import { ADMIN_SECTIONS } from '../../constants/views';

defineProps({
    adminSection: {
        type: String,
        required: true,
    },
    hasOrderReviewRoles: {
        type: Boolean,
        default: false,
    },
    hasMaxManagerRole: {
        type: Boolean,
        default: false,
    },
    hasMenuManagerRole: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['change']);
</script>

<template>
    <header class="z-20 shrink-0 border-b border-gray-200 bg-white safe-area-top">
        <nav class="flex" aria-label="Разделы админки">
            <button
                v-if="hasOrderReviewRoles"
                type="button"
                class="flex-1 border-b-2 px-4 py-2 text-sm font-medium transition"
                :class="
                    adminSection === ADMIN_SECTIONS.orders
                        ? 'border-max-primary text-max-primary'
                        : 'border-transparent text-max-muted hover:text-gray-700'
                "
                @click="$emit('change', ADMIN_SECTIONS.orders)"
            >
                Заказы
            </button>
            <button
                v-if="hasMaxManagerRole"
                type="button"
                class="flex-1 border-b-2 px-4 py-2 text-sm font-medium transition"
                :class="
                    adminSection === ADMIN_SECTIONS.manualOrders
                        ? 'border-max-primary text-max-primary'
                        : 'border-transparent text-max-muted hover:text-gray-700'
                "
                @click="$emit('change', ADMIN_SECTIONS.manualOrders)"
            >
                Ручные заказы
            </button>
            <button
                v-if="hasMenuManagerRole"
                type="button"
                class="flex-1 border-b-2 px-4 py-2 text-sm font-medium transition"
                :class="
                    adminSection === ADMIN_SECTIONS.menu
                        ? 'border-max-primary text-max-primary'
                        : 'border-transparent text-max-muted hover:text-gray-700'
                "
                @click="$emit('change', ADMIN_SECTIONS.menu)"
            >
                Меню
            </button>
        </nav>
    </header>
</template>
