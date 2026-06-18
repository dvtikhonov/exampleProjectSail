<script setup>
/**
 * Страница входа и регистрации (Sanctum cookie session).
 *
 * Режимы:
 * - Вход — email + password → POST /login.
 * - Регистрация — name, email, password, confirmation → POST /register.
 *
 * После успешной аутентификации роутер ведёт на home (HomeRedirect → settings).
 * Гостевой маршрут: авторизованных пользователей guard перенаправляет с /login.
 */
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import AuthForm from '../components/auth/AuthForm.vue';
import PageShell from '../components/layout/PageShell.vue';
import { useAuth } from '../composables/useAuth';

const router = useRouter();
const { login, register, isLoading, error } = useAuth();

/** true — форма регистрации, false — вход. */
const isRegisterMode = ref(false);

/** Общие поля формы; name и password_confirmation нужны только при регистрации. */
const form = reactive({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

/** Отправка login или register в зависимости от режима. */
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

/** Переключение вход ↔ регистрация; сбрасываем сообщение об ошибке. */
function toggleMode() {
    isRegisterMode.value = !isRegisterMode.value;
    error.value = null;
}
</script>

<template>
    <!-- Форма аутентификации: вход или регистрация -->
    <PageShell
        max-width="md"
        centered
    >
        <AuthForm
            v-model:form="form"
            :is-register-mode="isRegisterMode"
            :is-loading="isLoading"
            :error="error"
            @submit="onSubmit"
            @toggle-mode="toggleMode"
        />
    </PageShell>
</template>
