<script setup lang="ts">
definePageMeta({
    middleware: 'auth',
});

const { user, logout } = useAuth();

/** Выходит из сессии и перенаправляет на страницу входа. */
async function onLogout(): Promise<void> {
    await logout();
    await navigateTo('/login');
}
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-white">
                    Мои задачи
                </h1>
                <p
                    v-if="user"
                    class="mt-1 text-sm text-slate-400"
                >
                    {{ user.name }} · {{ user.email }}
                </p>
            </div>

            <button
                type="button"
                class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-300 transition hover:border-slate-500 hover:text-white"
                @click="onLogout"
            >
                Выйти
            </button>
        </div>

        <div class="rounded-xl border border-dashed border-slate-700 bg-slate-900/40 p-8 text-center">
            <p class="text-lg font-medium text-slate-200">
                Список задач пуст
            </p>
            <p class="mt-2 text-sm text-slate-400">
                Реализуйте CRUD для listtodos: миграция, API (DTO/Service/Repository), страница Nuxt.
            </p>
        </div>

        <div class="rounded-lg border border-indigo-500/30 bg-indigo-500/5 px-4 py-3 text-sm text-indigo-200">
            <p class="font-medium">
                TODO (кандидат)
            </p>
            <ul class="mt-2 list-inside list-disc space-y-1 text-indigo-200/80">
                <li>GET /api/listtodos — список задач текущего пользователя</li>
                <li>POST /api/listtodos — создание</li>
                <li>PUT/PATCH /api/listtodos/{id} — обновление</li>
                <li>DELETE /api/listtodos/{id} — удаление</li>
            </ul>
        </div>
    </div>
</template>
