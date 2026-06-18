<script setup>
/**
 * Форма входа и регистрации (presentational).
 *
 * Данные формы — v-model:form; отправка и переключение режима — событиями
 * submit / toggle-mode. Логика API — в родителе Login.vue + useAuth.
 */
import AuthErrorAlert from '../AuthErrorAlert.vue';
import PrimaryButton from '../ui/PrimaryButton.vue';

const form = defineModel('form', {
    type: Object,
    required: true,
});

defineProps({
    isRegisterMode: {
        type: Boolean,
        default: false,
    },
    isLoading: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: null,
    },
});

defineEmits(['submit', 'toggle-mode']);
</script>

<template>
    <div>
        <h1 class="text-center text-2xl font-semibold text-slate-900">
            {{ isRegisterMode ? 'Регистрация' : 'Вход' }}
        </h1>
        <p class="mt-2 text-center text-sm text-slate-500">
            Карта торговых точек — MVP
        </p>

        <form
            class="mt-8 space-y-4"
            @submit.prevent="$emit('submit')"
        >
            <!-- Поля регистрации скрыты в режиме входа -->
            <div v-if="isRegisterMode">
                <label
                    class="mb-1 block text-sm font-medium text-slate-700"
                    for="name"
                >
                    Имя
                </label>
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
                <label
                    class="mb-1 block text-sm font-medium text-slate-700"
                    for="email"
                >
                    Email
                </label>
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
                <label
                    class="mb-1 block text-sm font-medium text-slate-700"
                    for="password"
                >
                    Пароль
                </label>
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
                <label
                    class="mb-1 block text-sm font-medium text-slate-700"
                    for="password_confirmation"
                >
                    Подтверждение пароля
                </label>
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

            <PrimaryButton
                type="submit"
                :loading="isLoading"
                full-width
            >
                {{
                    isLoading
                        ? (isRegisterMode ? 'Регистрация…' : 'Вход…')
                        : (isRegisterMode ? 'Зарегистрироваться' : 'Войти')
                }}
            </PrimaryButton>
        </form>

        <button
            type="button"
            class="mt-4 w-full text-center text-sm text-sky-700 hover:underline"
            @click="$emit('toggle-mode')"
        >
            {{ isRegisterMode ? 'Уже есть аккаунт? Войти' : 'Нет аккаунта? Зарегистрироваться' }}
        </button>
    </div>
</template>
