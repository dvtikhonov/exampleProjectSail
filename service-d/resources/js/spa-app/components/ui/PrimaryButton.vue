<script setup>
/**
 * Унифицированная кнопка с вариантами primary/secondary и состоянием loading.
 *
 * При loading кнопка disabled и показывает inline-спиннер.
 */
import { computed } from 'vue';

const props = defineProps({
    loading: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    type: {
        type: String,
        default: 'button',
        validator: (value) => ['button', 'submit', 'reset'].includes(value),
    },
    variant: {
        type: String,
        default: 'primary',
        validator: (value) => ['primary', 'secondary'].includes(value),
    },
    fullWidth: {
        type: Boolean,
        default: false,
    },
});

const isDisabled = computed(() => props.disabled || props.loading);

const buttonClass = computed(() => {
    const base = [
        'inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-60',
    ];

    if (props.fullWidth) {
        base.push('flex w-full');
    }

    if (props.variant === 'secondary') {
        base.push('border border-slate-300 bg-white text-slate-700 hover:bg-slate-50');
    } else {
        base.push('bg-sky-600 text-white hover:bg-sky-700');
    }

    return base;
});

const spinnerClass = computed(() => (
    props.variant === 'secondary'
        ? 'inline-block h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-slate-600'
        : 'inline-block h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white'
));
</script>

<template>
    <button
        :type="type"
        :disabled="isDisabled"
        :class="buttonClass"
    >
        <span
            v-if="loading"
            :class="spinnerClass"
            aria-hidden="true"
        />
        <slot />
    </button>
</template>
