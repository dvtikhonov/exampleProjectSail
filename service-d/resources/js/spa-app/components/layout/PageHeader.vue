<script setup>
/**
 * Шапка страницы: eyebrow, заголовок, подзаголовок и навигационная ссылка.
 *
 * backLink / forwardLink — объекты { name, params?, label } для router-link.
 * Слот actions переопределяет ссылки по умолчанию.
 */
defineProps({
    eyebrow: {
        type: String,
        required: true,
    },
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: null,
    },
    backLink: {
        type: Object,
        default: null,
    },
    forwardLink: {
        type: Object,
        default: null,
    },
});
</script>

<template>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm font-medium uppercase tracking-wide text-sky-700">
                {{ eyebrow }}
            </p>
            <h1 class="mt-1 text-2xl font-semibold text-slate-900">
                {{ title }}
            </h1>
            <p
                v-if="subtitle"
                class="mt-1 text-sm text-slate-600"
            >
                {{ subtitle }}
            </p>
        </div>

        <slot name="actions">
            <router-link
                v-if="backLink"
                :to="{ name: backLink.name, params: backLink.params }"
                class="text-sm font-medium text-sky-700 hover:underline"
            >
                {{ backLink.label }}
            </router-link>
            <router-link
                v-else-if="forwardLink"
                :to="{ name: forwardLink.name, params: forwardLink.params }"
                class="text-sm font-medium text-sky-700 hover:underline"
            >
                {{ forwardLink.label }}
            </router-link>
        </slot>
    </div>
</template>
