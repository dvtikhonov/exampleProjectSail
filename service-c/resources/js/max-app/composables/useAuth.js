/**
 * Авторизация MAX mini-app через initData из Bridge.
 * Определяет режим UI: клиент или админ (по admin_roles).
 */
import { computed, ref } from 'vue';
import { authenticate, extractErrorMessage } from '../api/foodClient';
import { getInitData } from '../bridge/maxBridge';
import {
    ADMIN_SECTIONS,
    ROLE_ADDRESS,
    ROLE_COMPOSITION,
    ROLE_MENU,
} from '../constants/views';

/**
 * @returns {object} Состояние и методы авторизации
 */
export function useAuth() {
    const authLoading = ref(true);
    const authError = ref('');
    const adminRoles = ref([]);
    const adminScope = ref('address');
    const adminSection = ref(ADMIN_SECTIONS.orders);

    const hasOrderReviewRoles = computed(() =>
        adminRoles.value.includes(ROLE_ADDRESS)
        || adminRoles.value.includes(ROLE_COMPOSITION),
    );

    const hasMenuManagerRole = computed(() => adminRoles.value.includes(ROLE_MENU));

    const hasAdminRoles = computed(() =>
        hasOrderReviewRoles.value || hasMenuManagerRole.value,
    );

    const showAdminSectionSwitcher = computed(() =>
        hasOrderReviewRoles.value && hasMenuManagerRole.value,
    );

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
     * Определяет начальный раздел админки: заказы или меню.
     *
     * @param {string[]} roles
     * @returns {string}
     */
    function resolveDefaultAdminSection(roles) {
        const hasOrders = roles.includes(ROLE_ADDRESS) || roles.includes(ROLE_COMPOSITION);
        const hasMenu = roles.includes(ROLE_MENU);

        if (hasMenu && !hasOrders) {
            return ADMIN_SECTIONS.menu;
        }

        return ADMIN_SECTIONS.orders;
    }

    /** Авторизация по initData; заполняет adminRoles и начальный adminScope */
    async function initAuth() {
        authLoading.value = true;
        authError.value = '';

        try {
            const initData = getInitData();

            if (!initData) {
                throw new Error('Не удалось получить initData от MAX. Откройте приложение через MAX.');
            }

            const authData = await authenticate(initData);
            adminRoles.value = authData.user?.admin_roles ?? [];
            adminSection.value = resolveDefaultAdminSection(adminRoles.value);
            adminScope.value = resolveDefaultAdminScope(adminRoles.value);
        } catch (error) {
            authError.value = extractErrorMessage(error);
        } finally {
            authLoading.value = false;
        }
    }

    return {
        authLoading,
        authError,
        adminRoles,
        adminScope,
        adminSection,
        hasOrderReviewRoles,
        hasMenuManagerRole,
        hasAdminRoles,
        showAdminSectionSwitcher,
        initAuth,
    };
}
