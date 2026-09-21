<script setup>
/**
 * Корневой layout SPA: шапка + RouterView.
 */
import { RouterLink, RouterView } from 'vue-router';
</script>

<template>
    <div class="min-h-dvh bg-paper text-ink">
        <header class="border-b border-line bg-white/80 backdrop-blur">
            <div class="mx-auto flex max-w-3xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <RouterLink
                    to="/notes"
                    class="text-lg font-semibold tracking-tight text-ink hover:text-accent"
                >
                    Notes
                </RouterLink>
                <RouterLink
                    to="/notes/new"
                    class="rounded-md bg-accent px-3 py-1.5 text-sm font-medium text-white transition hover:bg-accent-hover"
                >
                    Новая заметка
                </RouterLink>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
            <RouterView />
        </main>
    </div>
</template>
