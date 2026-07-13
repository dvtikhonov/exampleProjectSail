<script setup lang="ts">
import { reactive, ref } from 'vue';
import type { LoginCredentials, RegisterPayload } from '~/types/auth';

const { user, fetchUser, login, register, isLoading, error } = useAuth();

const route = useRoute();
const isBootstrapping = ref(import.meta.client);
const isRegisterMode = ref(route.query.register === '1');

const form = reactive({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

/** Отправляет форму входа или регистрации и перенаправляет на /tasks при успехе. */
async function onSubmit(): Promise<void> {
    const ok = isRegisterMode.value
        ? await register(form as RegisterPayload)
        : await login(form as LoginCredentials);

    if (ok) {
        await navigateTo('/tasks');
    }
}

/** Переключает режим формы между входом и регистрацией. */
function toggleMode(): void {
    isRegisterMode.value = !isRegisterMode.value;
    error.value = null;
}

onMounted(async () => {
    try {
        await fetchUser();

        if (user.value) {
            await navigateTo('/tasks');
        }
    } finally {
        isBootstrapping.value = false;
    }
});
</script>

<template>
    <div class="mx-auto max-w-md space-y-6">
        <AppSpinner
            v-if="isBootstrapping"
            centered
            size="lg"
            label="Проверка сессии…"
        />

        <template v-else>
            <div class="space-y-2 text-center">
                <h1 class="text-2xl font-semibold text-white">
                    {{ isRegisterMode ? 'Регистрация' : 'Вход' }}
                </h1>
                <p class="text-sm text-slate-400">
                    Sanctum cookie session · Nuxt 3
                </p>
            </div>

            <div
                v-if="error"
                class="rounded-lg border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-200"
                role="alert"
            >
                {{ error }}
            </div>

            <form
                class="space-y-4 rounded-xl border border-slate-800 bg-slate-900/60 p-6 shadow-xl"
                @submit.prevent="onSubmit"
            >
            <div
                v-if="isRegisterMode"
                class="space-y-1"
            >
                <label
                    for="name"
                    class="block text-sm font-medium text-slate-300"
                >
                    Имя
                </label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    autocomplete="name"
                    required
                    class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white outline-none ring-indigo-500 focus:ring-2"
                    placeholder="Ваше имя"
                >
            </div>

            <div class="space-y-1">
                <label
                    for="email"
                    class="block text-sm font-medium text-slate-300"
                >
                    Email
                </label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    required
                    class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white outline-none ring-indigo-500 focus:ring-2"
                    placeholder="you@example.com"
                >
            </div>

            <div class="space-y-1">
                <label
                    for="password"
                    class="block text-sm font-medium text-slate-300"
                >
                    Пароль
                </label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    required
                    class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white outline-none ring-indigo-500 focus:ring-2"
                >
            </div>

            <div
                v-if="isRegisterMode"
                class="space-y-1"
            >
                <label
                    for="password_confirmation"
                    class="block text-sm font-medium text-slate-300"
                >
                    Подтверждение пароля
                </label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                    class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white outline-none ring-indigo-500 focus:ring-2"
                >
            </div>

            <button
                type="submit"
                :disabled="isLoading"
                class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 font-medium text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
            >
                <AppSpinner
                    v-if="isLoading"
                    size="sm"
                    variant="on-primary"
                />
                <span>{{ isLoading ? 'Отправка…' : (isRegisterMode ? 'Зарегистрироваться' : 'Войти') }}</span>
            </button>
        </form>

        <p class="text-center text-sm text-slate-400">
            <button
                type="button"
                class="text-indigo-400 underline-offset-2 hover:underline"
                @click="toggleMode"
            >
                {{ isRegisterMode ? 'Уже есть аккаунт? Войти' : 'Нет аккаунта? Зарегистрироваться' }}
            </button>
        </p>
        </template>
    </div>
</template>
