import { mockNuxtImport } from '@nuxt/test-utils/runtime';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import authMiddleware from '~/middleware/auth';

const { navigateToMock, fetchUserMock } = vi.hoisted(() => ({
    navigateToMock: vi.fn(),
    fetchUserMock: vi.fn(),
}));

const userRef = ref<{ id: number } | null>(null);
const hasCheckedSessionRef = ref(false);

mockNuxtImport('navigateTo', () => navigateToMock);

mockNuxtImport('useAuth', () => {
    return () => ({
        user: userRef,
        fetchUser: fetchUserMock,
        hasCheckedSession: hasCheckedSessionRef,
    });
});

describe('auth middleware', () => {
    beforeEach(() => {
        navigateToMock.mockReset();
        fetchUserMock.mockReset();
        userRef.value = null;
        hasCheckedSessionRef.value = false;
    });

    it('не проверяет сессию на /login', async () => {
        await authMiddleware({ path: '/login' } as never, {} as never);

        expect(fetchUserMock).not.toHaveBeenCalled();
        expect(navigateToMock).not.toHaveBeenCalled();
    });

    it('не проверяет сессию на /register', async () => {
        await authMiddleware({ path: '/register' } as never, {} as never);

        expect(fetchUserMock).not.toHaveBeenCalled();
        expect(navigateToMock).not.toHaveBeenCalled();
    });

    it('запрашивает пользователя, если сессия ещё не проверена', async () => {
        hasCheckedSessionRef.value = false;
        userRef.value = { id: 1 };

        await authMiddleware({ path: '/tasks' } as never, {} as never);

        expect(fetchUserMock).toHaveBeenCalledOnce();
        expect(navigateToMock).not.toHaveBeenCalled();
    });

    it('перенаправляет на /login, если пользователь не авторизован', async () => {
        hasCheckedSessionRef.value = true;
        userRef.value = null;

        await authMiddleware({ path: '/tasks' } as never, {} as never);

        expect(navigateToMock).toHaveBeenCalledWith('/login');
    });

    it('пропускает авторизованного пользователя', async () => {
        hasCheckedSessionRef.value = true;
        userRef.value = { id: 1 };

        await authMiddleware({ path: '/tasks' } as never, {} as never);

        expect(navigateToMock).not.toHaveBeenCalled();
    });
});
