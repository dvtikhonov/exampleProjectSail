<script setup lang="ts">
const props = withDefaults(defineProps<{
    size?: 'sm' | 'md' | 'lg';
    label?: string;
    centered?: boolean;
    variant?: 'default' | 'on-primary';
}>(), {
    size: 'md',
    centered: false,
    variant: 'default',
});

const sizeClasses: Record<'sm' | 'md' | 'lg', string> = {
    sm: 'h-4 w-4 border-[1.5px]',
    md: 'h-8 w-8 border-2',
    lg: 'h-12 w-12 border-[3px]',
};

const spinnerClasses = computed(() => {
    if (props.variant === 'on-primary') {
        return 'border-white/30 border-t-white';
    }

    return 'border-slate-600 border-t-indigo-400';
});

const labelClasses = computed(() => {
    if (props.size === 'sm') {
        return 'text-sm';
    }

    if (props.size === 'lg') {
        return 'text-base text-slate-300';
    }

    return 'text-sm text-slate-400';
});
</script>

<template>
    <div
        :class="centered
            ? 'flex flex-col items-center justify-center gap-3 py-8'
            : 'inline-flex items-center gap-2'"
        :aria-busy="true"
        :aria-label="label || 'Загрузка'"
    >
        <span
            class="inline-block animate-spin rounded-full"
            :class="[sizeClasses[size], spinnerClasses]"
            role="status"
        />
        <span
            v-if="label"
            :class="labelClasses"
        >
            {{ label }}
        </span>
    </div>
</template>
