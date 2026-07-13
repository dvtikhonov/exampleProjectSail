<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} — {{ ($registerMode ?? false) ? 'регистрация' : 'тестовый вход' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
<div class="mx-auto flex min-h-screen max-w-md items-center px-4 py-10">
    <div class="w-full space-y-6">
        <div class="space-y-2 text-center">
            <h1 id="page-title" class="text-2xl font-semibold">
                {{ ($registerMode ?? false) ? 'Регистрация' : 'Тестовый вход' }}
            </h1>
            <p class="text-sm text-slate-400">Sanctum cookie session · {{ config('app.name') }}</p>
        </div>

        <div
            id="error"
            class="hidden rounded-lg border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-200"
            role="alert"
        ></div>

        <div
            id="success"
            class="hidden rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200"
            role="status"
        ></div>

        <form id="auth-form" class="space-y-4 rounded-xl border border-slate-800 bg-slate-900/60 p-6 shadow-xl">
            <div id="name-field" class="{{ ($registerMode ?? false) ? '' : 'hidden ' }}space-y-1">
                <label for="name" class="block text-sm font-medium text-slate-300">Имя</label>
                <input
                    id="name"
                    name="name"
                    type="text"
                    autocomplete="name"
                    class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 outline-none ring-indigo-500 focus:ring-2"
                    placeholder="Ваше имя"
                >
            </div>

            <div class="space-y-1">
                <label for="email" class="block text-sm font-medium text-slate-300">Email</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    required
                    autocomplete="email"
                    class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 outline-none ring-indigo-500 focus:ring-2"
                    placeholder="you@example.com"
                >
            </div>

            <div class="space-y-1">
                <label for="password" class="block text-sm font-medium text-slate-300">Пароль</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    required
                    autocomplete="{{ ($registerMode ?? false) ? 'new-password' : 'current-password' }}"
                    class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 outline-none ring-indigo-500 focus:ring-2"
                >
            </div>

            <div id="password-confirmation-field" class="{{ ($registerMode ?? false) ? '' : 'hidden ' }}space-y-1">
                <label for="password_confirmation" class="block text-sm font-medium text-slate-300">Подтверждение пароля</label>
                <input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 outline-none ring-indigo-500 focus:ring-2"
                >
            </div>

            <button
                type="submit"
                id="submit-btn"
                class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 font-medium transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
            >
                {{ ($registerMode ?? false) ? 'Зарегистрироваться' : 'Войти' }}
            </button>
        </form>

        <p class="text-center text-sm text-slate-400">
            <button
                type="button"
                id="toggle-mode-btn"
                class="text-indigo-400 underline-offset-2 hover:underline"
            >
                {{ ($registerMode ?? false) ? 'Уже есть аккаунт? Войти' : 'Нет аккаунта? Зарегистрироваться' }}
            </button>
        </p>

        <p class="text-center text-xs text-slate-500">
            UI через gateway:
            <a href="{{ rtrim((string) env('FRONTEND_URL', config('app.url')), '/') }}/login" class="text-indigo-400 hover:underline">
                {{ rtrim((string) env('FRONTEND_URL', config('app.url')), '/') }}/login
            </a>
        </p>
    </div>
</div>

<script>
    const form = document.getElementById('auth-form');
    const errorBox = document.getElementById('error');
    const successBox = document.getElementById('success');
    const submitBtn = document.getElementById('submit-btn');
    const toggleModeBtn = document.getElementById('toggle-mode-btn');
    const pageTitle = document.getElementById('page-title');
    const nameField = document.getElementById('name-field');
    const passwordConfirmationField = document.getElementById('password-confirmation-field');

    let isRegisterMode = @json((bool) ($registerMode ?? false));

    /** Читает значение cookie по имени. */
    function getCookie(name) {
        const match = document.cookie.match(new RegExp('(?:^|;\\s*)' + name + '=([^;]+)'));

        return match ? decodeURIComponent(match[1]) : null;
    }

    /** Показывает сообщение об ошибке и скрывает блок успеха. */
    function showError(message) {
        errorBox.textContent = message;
        errorBox.classList.remove('hidden');
        successBox.classList.add('hidden');
    }

    /** Показывает сообщение об успехе и скрывает блок ошибки. */
    function showSuccess(message) {
        successBox.textContent = message;
        successBox.classList.remove('hidden');
        errorBox.classList.add('hidden');
    }

    /** Обновляет UI формы при переключении режима вход/регистрация. */
    function updateModeUi() {
        pageTitle.textContent = isRegisterMode ? 'Регистрация' : 'Тестовый вход';
        submitBtn.textContent = isRegisterMode ? 'Зарегистрироваться' : 'Войти';
        toggleModeBtn.textContent = isRegisterMode ? 'Уже есть аккаунт? Войти' : 'Нет аккаунта? Зарегистрироваться';
        nameField.classList.toggle('hidden', !isRegisterMode);
        passwordConfirmationField.classList.toggle('hidden', !isRegisterMode);
        form.password.autocomplete = isRegisterMode ? 'new-password' : 'current-password';
        errorBox.classList.add('hidden');
        successBox.classList.add('hidden');
    }

    toggleModeBtn.addEventListener('click', () => {
        // Переключает режим вход/регистрация без перезагрузки страницы.
        isRegisterMode = !isRegisterMode;
        updateModeUi();
    });

    form.addEventListener('submit', async (event) => {
        // Отправляет форму через Sanctum: CSRF-cookie → POST /api/auth/login|register.
        event.preventDefault();
        submitBtn.disabled = true;
        errorBox.classList.add('hidden');
        successBox.classList.add('hidden');

        try {
            await fetch('/sanctum/csrf-cookie', {
                credentials: 'include',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const xsrfToken = getCookie('XSRF-TOKEN');
            const endpoint = isRegisterMode ? '/api/auth/register' : '/api/auth/login';
            const payload = isRegisterMode
                ? {
                    name: form.name.value,
                    email: form.email.value,
                    password: form.password.value,
                    password_confirmation: form.password_confirmation.value,
                }
                : {
                    email: form.email.value,
                    password: form.password.value,
                };

            const response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                const firstError = data.errors
                    ? Object.values(data.errors).flat()[0]
                    : null;
                const message = data.message || firstError || (isRegisterMode ? 'Не удалось зарегистрироваться.' : 'Не удалось войти.');

                showError(message);

                return;
            }

            const user = data.user ?? {};
            showSuccess(
                isRegisterMode
                    ? `Аккаунт создан: ${user.name ?? user.email ?? 'пользователь'}.`
                    : `Вы вошли как ${user.name ?? user.email ?? 'пользователь'}.`,
            );
        } catch (error) {
            showError('Ошибка сети. Проверьте, что service-g запущен.');
        } finally {
            submitBtn.disabled = false;
        }
    });
</script>
</body>
</html>
