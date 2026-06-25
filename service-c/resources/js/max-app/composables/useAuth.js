/**
 * Авторизация MAX mini-app через initData из Bridge.
 * Определяет режим UI: клиент или админ (по admin_roles).
 */
import { computed, ref } from 'vue';
import { authenticate, extractErrorMessage } from '../api/foodClient';
import { getInitData } from '../bridge/maxBridge';
import { ROLE_ADDRESS, ROLE_COMPOSITION } from '../constants/views';

/**
 * @returns {object} Состояние и методы авторизации
 */
export function useAuth() {
    const authLoading = ref(true);
    const authError = ref('');
    const adminRoles = ref([]);
    const adminScope = ref('address');

    const hasAdminRoles = computed(() => adminRoles.value.length > 0);

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
        hasAdminRoles,
        initAuth,
    };
}
