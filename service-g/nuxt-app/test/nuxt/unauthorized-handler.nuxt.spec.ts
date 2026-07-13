import { mockNuxtImport } from '@nuxt/test-utils/runtime';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { computed, ref } from 'vue';
import { useUnauthorizedHandler } from '~/composables/useUnauthorizedHandler';

const { pushMock } = vi.hoisted(() => ({
    pushMock: vi.fn(),
}));

const userRef = ref<{ id: number } | null>({ id: 1 });
const hasCheckedSessionRef = ref(true);
const currentPathRef = ref('/tasks');

mockNuxtImport('useAuth', () => {
    return () => ({
        user: userRef,
        hasCheckedSession: hasCheckedSessionRef,
    });
});

mockNuxtImport('useRouter', () => {
    return () => ({
        currentRoute: computed(() => ({ path: currentPathRef.value })),
        push: pushMock,
    });
});

describe('useUnauthorizedHandler', () => {
    beforeEach(() => {
        pushMock.mockReset();
        userRef.value = { id: 1 };
        hasCheckedSessionRef.value = false;
        currentPathRef.value = '/tasks';
    });

    it('игнорирует 401 на /auth/login', () => {
        const { handleUnauthorized } = useUnauthorizedHandler();

        handleUnauthorized('/api/auth/login');

        expect(userRef.value).toEqual({ id: 1 });
        expect(pushMock).not.toHaveBeenCalled();
    });

    it('игнорирует 401 на /sanctum/csrf-cookie', () => {
        const { handleUnauthorized } = useUnauthorizedHandler();

        handleUnauthorized('/sanctum/csrf-cookie');

        expect(pushMock).not.toHaveBeenCalled();
    });

    it('игнорирует 401 на /api/user (проверка сессии)', () => {
        const { handleUnauthorized } = useUnauthorizedHandler();

        handleUnauthorized('/api/user');

        expect(userRef.value).toEqual({ id: 1 });
        expect(pushMock).not.toHaveBeenCalled();
    });

    it('сбрасывает сессию и перенаправляет на /login при 401 API-запроса', () => {
        const { handleUnauthorized } = useUnauthorizedHandler();

        handleUnauthorized('/api/tasks');

        expect(userRef.value).toBeNull();
        expect(hasCheckedSessionRef.value).toBe(true);
        expect(pushMock).toHaveBeenCalledWith('/login');
    });

    it('не редиректит повторно, если уже на /login', () => {
        currentPathRef.value = '/login';

        const { handleUnauthorized } = useUnauthorizedHandler();

        handleUnauthorized('/api/tasks');

        expect(userRef.value).toBeNull();
        expect(pushMock).not.toHaveBeenCalled();
    });
});
