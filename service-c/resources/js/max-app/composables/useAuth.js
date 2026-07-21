/**
 * Авторизация MAX mini-app через initData из Bridge.
 * Определяет режим UI: клиент или админ (по admin_roles).
 * Состояние общее для всех вызовов useAuth() (singleton).
 */
import { computed, ref } from 'vue';
import { authenticate, extractErrorMessage } from '../api/foodClient';
import { getInitData } from '../bridge/maxBridge';
import {
    ADMIN_SECTIONS,
    ROLE_ADDRESS,
    ROLE_COMPOSITION,
    ROLE_MAX_MANAGER,
    ROLE_MENU,
} from '../constants/views';

const authLoading = ref(true);
const authError = ref('');
const maxUserId = ref(null);
const adminRoles = ref([]);
const adminScope = ref('address');
const adminSection = ref(ADMIN_SECTIONS.orders);

const hasOrderReviewRoles = computed(() =>
    adminRoles.value.includes(ROLE_ADDRESS)
    || adminRoles.value.includes(ROLE_COMPOSITION),
);

const hasMenuManagerRole = computed(() => adminRoles.value.includes(ROLE_MENU));

const hasMaxManagerRole = computed(() => adminRoles.value.includes(ROLE_MAX_MANAGER));

const hasAdminRoles = computed(() =>
    hasOrderReviewRoles.value || hasMenuManagerRole.value || hasMaxManagerRole.value,
);

/** Доступные вкладки админки по ролям */
const availableAdminSections = computed(() => {
    const sections = [];

    if (hasOrderReviewRoles.value) {
        sections.push(ADMIN_SECTIONS.orders);
    }

    if (hasMaxManagerRole.value) {
        sections.push(ADMIN_SECTIONS.manualOrders);
    }

    if (hasMenuManagerRole.value) {
        sections.push(ADMIN_SECTIONS.menu);
    }

    return sections;
});

const showAdminSectionSwitcher = computed(() => availableAdminSections.value.length > 1);

/**
 * Выбирает вкладку админки по приоритету ролей пользователя.
 *
 * @param {string[]} roles
 * @returns {'address'|'composition'}
 */
function resolveDefaultAdminScope(roles) {
    if (roles.includes(ROLE_ADDRESS)) {
        return 'address';
    }

    if (roles.includes(ROLE_COMPOSITION)) {
        return 'composition';
    }

    return 'address';
}

/**
 * Определяет начальный раздел админки: заказы, ручные заказы или меню.
 *
 * @param {string[]} roles
 * @returns {string}
 */
function resolveDefaultAdminSection(roles) {
    const hasOrders = roles.includes(ROLE_ADDRESS) || roles.includes(ROLE_COMPOSITION);
    const hasManual = roles.includes(ROLE_MAX_MANAGER);
    const hasMenu = roles.includes(ROLE_MENU);

    if (hasOrders) {
        return ADMIN_SECTIONS.orders;
    }

    if (hasManual) {
        return ADMIN_SECTIONS.manualOrders;
    }

    if (hasMenu) {
        return ADMIN_SECTIONS.menu;
    }

    return ADMIN_SECTIONS.orders;
}

/** Авторизация по initData; заполняет maxUserId, adminRoles и начальный adminScope */
async function initAuth() {
    authLoading.value = true;
    authError.value = '';

    try {
        const initData = getInitData();

        if (!initData) {
            throw new Error('Не удалось получить initData от MAX. Откройте приложение через MAX.');
        }

        const authData = await authenticate(initData);
        const userId = authData.user?.max_user_id;
        maxUserId.value = typeof userId === 'number' ? userId : null;
        adminRoles.value = authData.user?.admin_roles ?? [];
        adminSection.value = resolveDefaultAdminSection(adminRoles.value);
        adminScope.value = resolveDefaultAdminScope(adminRoles.value);
    } catch (error) {
        maxUserId.value = null;
        authError.value = extractErrorMessage(error);
    } finally {
        authLoading.value = false;
    }
}

/**
 * @returns {object} Состояние и методы авторизации
 */
export function useAuth() {
    return {
        authLoading,
        authError,
        maxUserId,
        adminRoles,
        adminScope,
        adminSection,
        hasOrderReviewRoles,
        hasMenuManagerRole,
        hasMaxManagerRole,
        hasAdminRoles,
        availableAdminSections,
        showAdminSectionSwitcher,
        initAuth,
    };
}
