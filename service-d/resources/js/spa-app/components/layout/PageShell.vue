<script setup>
/**
 * Обёртка страницы: центрированная карточка с настраиваемой шириной.
 *
 * centered — вертикальное центрирование (логин); maxWidth — md/lg/2xl/4xl.
 */
import { computed } from 'vue';

const props = defineProps({
    maxWidth: {
        type: String,
        default: '2xl',
        validator: (value) => ['md', 'lg', '2xl', '4xl'].includes(value),
    },
    centered: {
        type: Boolean,
        default: false,
    },
    cardPadding: {
        type: String,
        default: '8',
        validator: (value) => ['8', '10'].includes(value),
    },
});

const outerClass = computed(() => [
    'flex min-h-dvh justify-center px-4 py-10',
    props.centered ? 'items-center' : 'items-start',
]);

const innerClass = computed(() => {
    const maxWidthClass = {
        md: 'max-w-md',
        lg: 'max-w-lg',
        '2xl': 'max-w-2xl',
        '4xl': 'max-w-4xl',
    }[props.maxWidth];

    const paddingClass = props.cardPadding === '10' ? 'p-10' : 'p-8';

    return [
        'w-full rounded-2xl border border-slate-200 bg-white shadow-sm',
        maxWidthClass,
        paddingClass,
    ];
});
</script>

<template>
    <div :class="outerClass">
        <div :class="innerClass">
            <slot />
        </div>
    </div>
</template>
