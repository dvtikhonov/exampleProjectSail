/**
 * Ручные заказы (роль max_manager): выбор потребителя и контекст targetMaxUserId.
 * Корзина/submit идут через admin manual-orders API при активном target.
 */
import { computed, onScopeDispose, ref } from 'vue';
import { extractErrorMessage, fetchManualOrderUsers } from '../api/foodClient';
import { formatCustomerFio } from '../utils/formatCustomerName';

/** Задержка debounce поиска пользователей (мс) */
const SEARCH_DEBOUNCE_MS = 300;

/**
 * Подпись потребителя с ФИО для шапки ручного заказа.
 *
 * @param {object|null|undefined} user — элемент из GET manual-orders/users
 * @returns {string}
 */
export function formatManualOrderCustomerLabel(user) {
    if (!user || typeof user !== 'object') {
        return '';
    }

    const fio = formatCustomerFio(user);
    const name = fio !== '' ? fio : 'Потребитель';
    const username = typeof user.username === 'string' && user.username.trim() !== ''
        ? user.username.trim().replace(/^@/, '')
        : '';

    return username !== '' ? `${name} (@${username})` : name;
}

/**
 * @returns {object} Состояние и методы раздела «Ручные заказы»
 */
export function useManualOrder() {
    const targetMaxUserId = ref(null);
    /** @type {import('vue').Ref<object|null>} */
    const targetUser = ref(null);
    const users = ref([]);
    const usersLoading = ref(false);
    const usersError = ref('');
    const usersQuery = ref('');

    /** @type {ReturnType<typeof setTimeout>|null} */
    let searchDebounceTimer = null;

    const isOrdering = computed(() => targetMaxUserId.value !== null);

    const customerLabel = computed(() => formatManualOrderCustomerLabel(targetUser.value));

    /**
     * @param {{ q?: string }} [options]
     */
    async function loadUsers({ q } = {}) {
        const query = typeof q === 'string' ? q : usersQuery.value;

        usersLoading.value = true;
        usersError.value = '';

        try {
            users.value = await fetchManualOrderUsers({ q: query });
        } catch (error) {
            usersError.value = extractErrorMessage(error);
            users.value = [];
        } finally {
            usersLoading.value = false;
        }
    }

    /**
     * @param {string} query
     */
    function handleUsersSearchInput(query) {
        usersQuery.value = query;

        if (searchDebounceTimer !== null) {
            clearTimeout(searchDebounceTimer);
        }

        searchDebounceTimer = setTimeout(() => {
            searchDebounceTimer = null;
            loadUsers({ q: usersQuery.value });
        }, SEARCH_DEBOUNCE_MS);
    }

    /**
     * @param {{ max_user_id?: number|string }} user
     */
    function selectUser(user) {
        const rawId = user?.max_user_id;
        const id = typeof rawId === 'number' ? rawId : Number(rawId);

        if (!Number.isFinite(id) || id <= 0) {
            return;
        }

        targetMaxUserId.value = id;
        targetUser.value = user;
    }

    function clearTargetUser() {
        targetMaxUserId.value = null;
        targetUser.value = null;
    }

    /** Сброс выбора и загрузка списка при входе в раздел */
    function initManualOrderSession() {
        clearTargetUser();
        usersQuery.value = '';
        loadUsers({ q: '' });
    }

    /**
     * max_user_id клиента для manual cart API или null вне режима оформления.
     *
     * @returns {number|null}
     */
    function getTargetMaxUserId() {
        return targetMaxUserId.value;
    }

    onScopeDispose(() => {
        if (searchDebounceTimer !== null) {
            clearTimeout(searchDebounceTimer);
            searchDebounceTimer = null;
        }
    });

    return {
        targetMaxUserId,
        targetUser,
        customerLabel,
        isOrdering,
        users,
        usersLoading,
        usersError,
        usersQuery,
        loadUsers,
        handleUsersSearchInput,
        selectUser,
        clearTargetUser,
        initManualOrderSession,
        getTargetMaxUserId,
    };
}
