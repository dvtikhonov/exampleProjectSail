<x-guest-layout>
    <div>
        <h1 class="text-center text-2xl font-semibold text-slate-900">
            Вход
        </h1>
        <p class="mt-2 text-center text-sm text-slate-500">
            Сокращатель ссылок
        </p>
    </div>

    <x-auth-session-status class="mt-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-4">
        @csrf

        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="Пароль" />
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex items-center">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-slate-300 text-sky-600 shadow-sm focus:ring-sky-500" name="remember">
                <span class="ms-2 text-sm text-slate-600">Запомнить меня</span>
            </label>
        </div>

        <x-primary-button class="flex w-full justify-center">
            Войти
        </x-primary-button>
    </form>

    @if (Route::has('register'))
        <a
            href="{{ route('register') }}"
            class="mt-4 block w-full text-center text-sm text-sky-700 hover:underline"
        >
            Нет аккаунта? Зарегистрироваться
        </a>
    @endif
</x-guest-layout>
