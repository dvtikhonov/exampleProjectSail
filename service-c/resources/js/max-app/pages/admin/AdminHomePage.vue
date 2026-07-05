<script setup>
/**
 * Оболочка админ-интерфейса: заголовок и вкладки «Адреса» / «Состав»
 * в зависимости от admin_roles пользователя.
 */
import { computed } from 'vue';
import AdminOrderListPage from './AdminOrderListPage.vue';

const ROLE_ADDRESS = 'address_reviewer';
const ROLE_COMPOSITION = 'composition_reviewer';

const props = defineProps({
    adminRoles: {
        type: Array,
        default: () => [],
    },
    activeScope: {
        type: String,
        required: true,
        validator: (value) => ['address', 'composition'].includes(value),
    },
    orders: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
    refreshing: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['change-scope', 'select-order', 'refresh']);

const tabs = computed(() => {
    const items = [];

    if (props.adminRoles.includes(ROLE_ADDRESS)) {
        items.push({ scope: 'address', label: 'Адреса' });
    }

    if (props.adminRoles.includes(ROLE_COMPOSITION)) {
        items.push({ scope: 'composition', label: 'Состав' });
    }

    return items;
});

const showTabs = computed(() => tabs.value.length > 1);
</script>

<template>
    <div class="flex h-full min-h-0 flex-col">
        <header class="sticky top-0 z-10 border-b border-gray-200 bg-white safe-area-top">
            <div class="px-4 py-3">
                <h1 class="text-lg font-semibold text-gray-900">Проверка заказов</h1>
                <p class="text-sm text-max-muted">Очередь на подтверждение</p>
            </div>

            <nav
                v-if="showTabs"
                class="flex border-t border-gray-100"
                aria-label="Разделы проверки"
            >
                <button
                    v-for="tab in tabs"
                    :key="tab.scope"
                    type="button"
                    class="flex-1 border-b-2 px-4 py-3 text-sm font-medium transition"
                    :class="
                        activeScope === tab.scope
                            ? 'border-max-primary text-max-primary'
                            : 'border-transparent text-max-muted hover:text-gray-700'
                    "
                    @click="emit('change-scope', tab.scope)"
                >
                    {{ tab.label }}
                </button>
            </nav>

            <div
                v-else-if="tabs.length === 1"
                class="border-t border-gray-100 px-4 py-2 text-sm font-medium text-max-primary"
            >
                {{ tabs[0].label }}
            </div>
        </header>

        <AdminOrderListPage
            :orders="orders"
            :loading="loading"
            :error="error"
            :refreshing="refreshing"
            @select-order="(order) => emit('select-order', order)"
            @refresh="emit('refresh')"
        />
    </div>
</template>
