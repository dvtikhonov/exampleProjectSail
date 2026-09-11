<script setup>
/**
 * Оболочка раздела «Заказы»: вкладки «Проверка заказов» / «Отчёты»,
 * внутри проверки — «Адреса» / «Состав» по admin_roles.
 */
import { computed } from 'vue';
import FoodReportForm from '../../components/admin/FoodReportForm.vue';
import { ADMIN_ORDERS_PANELS } from '../../constants/views';
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
    /** Вкладка раздела: проверка очереди или отчёты */
    activePanel: {
        type: String,
        required: true,
        validator: (value) => Object.values(ADMIN_ORDERS_PANELS).includes(value),
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
    /** Доступна вкладка выгрузки отчётов (роль max_manager) */
    showReports: {
        type: Boolean,
        default: false,
    },
    /** Доступна вкладка очереди проверки (роли address/composition) */
    showOrderQueue: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['change-scope', 'change-panel', 'select-order', 'refresh']);

const panelTabs = computed(() => {
    const items = [];

    if (props.showOrderQueue) {
        items.push({
            panel: ADMIN_ORDERS_PANELS.review,
            label: 'Проверка заказов',
            hint: 'Очередь на подтверждение',
        });
    }

    if (props.showReports) {
        items.push({
            panel: ADMIN_ORDERS_PANELS.reports,
            label: 'Отчёты',
            hint: 'Выгрузка отчётов',
        });
    }

    return items;
});

const showPanelTabs = computed(() => panelTabs.value.length > 1);

const scopeTabs = computed(() => {
    const items = [];

    if (props.adminRoles.includes(ROLE_ADDRESS)) {
        items.push({ scope: 'address', label: 'Адреса' });
    }

    if (props.adminRoles.includes(ROLE_COMPOSITION)) {
        items.push({ scope: 'composition', label: 'Состав' });
    }

    return items;
});

const showScopeTabs = computed(() => scopeTabs.value.length > 1);

const isReviewPanel = computed(() => props.activePanel === ADMIN_ORDERS_PANELS.review);
const isReportsPanel = computed(() => props.activePanel === ADMIN_ORDERS_PANELS.reports);

const pageTitle = computed(() => {
    if (showPanelTabs.value) {
        return 'Заказы';
    }

    return isReportsPanel.value ? 'Отчёты' : 'Проверка заказов';
});
</script>

<template>
    <div class="flex h-full min-h-0 flex-col">
        <header class="sticky top-0 z-10 border-b border-gray-200 bg-white">
            <div class="px-4 py-3">
                <h1 class="text-lg font-semibold text-gray-900">{{ pageTitle }}</h1>
            </div>

            <nav
                v-if="showPanelTabs"
                class="flex border-t border-gray-100"
                aria-label="Разделы заказов"
            >
                <button
                    v-for="tab in panelTabs"
                    :key="tab.panel"
                    type="button"
                    class="group relative flex-1 border-b-2 px-4 py-3 text-sm font-medium transition hover:z-20"
                    :class="
                        activePanel === tab.panel
                            ? 'border-max-primary text-max-primary'
                            : 'border-transparent text-max-muted hover:text-gray-700'
                    "
                    :aria-label="`${tab.label}. ${tab.hint}`"
                    @click="emit('change-panel', tab.panel)"
                >
                    {{ tab.label }}
                    <span
                        class="pointer-events-none absolute bottom-full left-1/2 z-50 mb-1.5 -translate-x-1/2 whitespace-nowrap rounded-xl bg-gray-900 px-3 py-1.5 text-xs font-medium text-white opacity-0 shadow-lg transition-opacity duration-150 group-hover:opacity-100"
                        role="tooltip"
                    >
                        {{ tab.hint }}
                        <span
                            class="absolute -bottom-1 left-1/2 h-2 w-2 -translate-x-1/2 rotate-45 bg-gray-900"
                            aria-hidden="true"
                        />
                    </span>
                </button>
            </nav>

            <nav
                v-if="isReviewPanel && showOrderQueue && showScopeTabs"
                class="flex border-t border-gray-100"
                aria-label="Разделы проверки"
            >
                <button
                    v-for="tab in scopeTabs"
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
                v-else-if="isReviewPanel && showOrderQueue && scopeTabs.length === 1"
                class="border-t border-gray-100 px-4 py-2 text-sm font-medium text-max-primary"
            >
                {{ scopeTabs[0].label }}
            </div>
        </header>

        <div class="flex min-h-0 flex-1 flex-col">
            <div
                v-if="isReportsPanel && showReports"
                class="min-h-0 flex-1 overflow-y-auto px-4 py-4"
            >
                <FoodReportForm />
            </div>

            <AdminOrderListPage
                v-if="isReviewPanel && showOrderQueue"
                class="min-h-0 flex-1"
                :orders="orders"
                :loading="loading"
                :error="error"
                :refreshing="refreshing"
                @select-order="(order) => emit('select-order', order)"
                @refresh="emit('refresh')"
            />
        </div>
    </div>
</template>
