<script setup>
import { useRouter } from 'vue-router';
import AuthErrorAlert from '../components/AuthErrorAlert.vue';
import { useAuth } from '../composables/useAuth';

const router = useRouter();
const { user, logout, clearAuthState, isLoading, error } = useAuth();

async function onLogout() {
    await logout();
    clearAuthState();
    await router.push({ name: 'login' });
}
</script>

<template>
    <div class="flex min-h-dvh flex-col items-center justify-center px-4 py-10 text-center">
        <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-10 shadow-sm">
            <p class="text-sm font-medium uppercase tracking-wide text-sky-700">
                service-d
            </p>
            <h1 class="mt-3 text-3xl font-semibold text-slate-900">
                Карта торговых точек
            </h1>
            <p class="mt-4 text-base text-slate-600">
                Скоро здесь будет карта. Пока это заставка MVP без внешних SDK.
            </p>

            <p
                v-if="user"
                class="mt-6 text-sm text-slate-500"
            >
                Вы вошли как <span class="font-medium text-slate-800">{{ user.name }}</span>
                ({{ user.email }})
            </p>

            <div class="mt-8 space-y-3">
                <AuthErrorAlert :message="error" />

                <button
                    type="button"
                    :disabled="isLoading"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                    @click="onLogout"
                >
                    <span
                        v-if="isLoading"
                        class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-slate-600"
                        aria-hidden="true"
                    />
                    <span>{{ isLoading ? 'Выход…' : 'Выйти' }}</span>
                </button>
            </div>
        </div>
    </div>
</template>
