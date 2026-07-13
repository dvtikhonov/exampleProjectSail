<template>
    <div class="min-h-dvh bg-slate-950 text-slate-100">
        <header class="border-b border-slate-800 bg-slate-900/80 backdrop-blur">
            <div class="mx-auto flex max-w-4xl items-center justify-between px-4 py-4">
                <NuxtLink
                    to="/tasks"
                    class="text-lg font-semibold tracking-tight text-white"
                >
                    service-g · To-Do
                </NuxtLink>

                <nav class="flex items-center gap-3 text-sm">
                    <NuxtLink
                        to="/tasks"
                        class="rounded-md px-3 py-1.5 text-slate-300 transition hover:bg-slate-800 hover:text-white"
                    >
                        Задачи
                    </NuxtLink>
                    <NuxtLink
                        to="/login"
                        class="rounded-md px-3 py-1.5 text-slate-300 transition hover:bg-slate-800 hover:text-white"
                    >
                        Вход
                    </NuxtLink>
                    <NuxtLink
                        to="/register"
                        class="rounded-md px-3 py-1.5 text-slate-300 transition hover:bg-slate-800 hover:text-white"
                    >
                        Регистрация
                    </NuxtLink>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-4xl px-4 py-8">
            <slot />
        </main>
    </div>
</template>
