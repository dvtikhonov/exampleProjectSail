<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import AuthErrorAlert from '../components/AuthErrorAlert.vue';
import { useAuth } from '../composables/useAuth';

const router = useRouter();
const { login, register, isLoading, error } = useAuth();

const isRegisterMode = ref(false);

const form = reactive({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

async function onSubmit() {
    const ok = isRegisterMode.value
        ? await register({
            name: form.name,
            email: form.email,
            password: form.password,
            password_confirmation: form.password_confirmation,
        })
        : await login({
            email: form.email,
            password: form.password,
        });

    if (ok) {
        await router.push({ name: 'home' });
    }
}

function toggleMode() {
    isRegisterMode.value = !isRegisterMode.value;
    error.value = null;
}
</script>

<template>
    <div class="flex min-h-dvh items-center justify-center px-4 py-10">
        <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
            <h1 class="text-center text-2xl font-semibold text-slate-900">
                {{ isRegisterMode ? 'Регистрация' : 'Вход' }}
            </h1>
            <p class="mt-2 text-center text-sm text-slate-500">
                Карта торговых точек — MVP
            </p>

            <form class="mt-8 space-y-4" @submit.prevent="onSubmit">
                <div v-if="isRegisterMode">
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="name">Имя</label>
                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        required
                        autocomplete="name"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-sky-500 focus:ring-2"
                    >
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="email">Email</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        autocomplete="email"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-sky-500 focus:ring-2"
                    >
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="password">Пароль</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        required
                        :autocomplete="isRegisterMode ? 'new-password' : 'current-password'"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-sky-500 focus:ring-2"
                    >
                </div>

                <div v-if="isRegisterMode">
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="password_confirmation">Подтверждение пароля</label>
                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        required
                        autocomplete="new-password"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-sky-500 focus:ring-2"
                    >
                </div>

                <AuthErrorAlert :message="error" />

                <button
                    type="submit"
                    :disabled="isLoading"
                    class="flex w-full items-center justify-center gap-2 rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <span
                        v-if="isLoading"
                        class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
                        aria-hidden="true"
                    />
                    <span>{{ isLoading ? (isRegisterMode ? 'Регистрация…' : 'Вход…') : (isRegisterMode ? 'Зарегистрироваться' : 'Войти') }}</span>
                </button>
            </form>

            <button
                type="button"
                class="mt-4 w-full text-center text-sm text-sky-700 hover:underline"
                @click="toggleMode"
            >
                {{ isRegisterMode ? 'Уже есть аккаунт? Войти' : 'Нет аккаунта? Зарегистрироваться' }}
            </button>
        </div>
    </div>
</template>
