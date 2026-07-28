<script setup>
/**
 * Нейтральные SVG-заглушки для empty states и fallback-превью (без эмодзи).
 *
 * @typedef {'restaurant' | 'cart' | 'list' | 'lock' | 'check' | 'clock'} EmptyStateIconName
 * @typedef {'sm' | 'md' | 'lg' | 'xl'} EmptyStateIconSize
 */
import { computed } from 'vue';

const props = defineProps({
    /** @type {{ type: StringConstructor, required: true }} */
    name: {
        type: String,
        required: true,
        validator: (value) => ['restaurant', 'cart', 'list', 'lock', 'check', 'clock'].includes(value),
    },
    /** @type {{ type: StringConstructor, default: string }} */
    size: {
        type: String,
        default: 'md',
        validator: (value) => ['sm', 'md', 'lg', 'xl'].includes(value),
    },
});

const sizeClass = computed(() => {
    if (props.size === 'sm') {
        return 'h-5 w-5';
    }

    if (props.size === 'lg') {
        return 'h-10 w-10';
    }

    if (props.size === 'xl') {
        return 'h-12 w-12';
    }

    return 'h-6 w-6';
});
</script>

<template>
    <svg
        class="shrink-0 text-gray-400"
        :class="sizeClass"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
        stroke-width="1.5"
        aria-hidden="true"
    >
        <!-- restaurant / dish: building-storefront -->
        <template v-if="name === 'restaurant'">
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72l1.189-1.19A3 3 0 015.378 3h13.243a3 3 0 012.16.89l1.189 1.19a3.004 3.004 0 01-.621 4.72M6.75 18h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .414.336.75.75.75z"
            />
        </template>

        <!-- cart -->
        <template v-else-if="name === 'cart'">
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"
            />
        </template>

        <!-- list / orders -->
        <template v-else-if="name === 'list'">
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"
            />
        </template>

        <!-- lock -->
        <template v-else-if="name === 'lock'">
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"
            />
        </template>

        <!-- check -->
        <template v-else-if="name === 'check'">
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M4.5 12.75l6 6 9-13.5"
            />
        </template>

        <!-- clock -->
        <template v-else>
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"
            />
        </template>
    </svg>
</template>
