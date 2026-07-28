<script setup>
/**
 * Превью блюда: lazy-load изображения с fallback на SVG при ошибке или отсутствии URL.
 */
import { computed, ref, watch } from 'vue';
import EmptyStateIcon from './EmptyStateIcon.vue';

const props = defineProps({
    imageUrl: {
        type: String,
        default: null,
    },
    alt: {
        type: String,
        default: '',
    },
    size: {
        type: String,
        default: 'md',
        validator: (value) => ['sm', 'md', 'lg'].includes(value),
    },
});

const broken = ref(false);

const showImage = computed(() => Boolean(props.imageUrl) && !broken.value);

const sizeClasses = computed(() => {
    if (props.size === 'sm') {
        return 'h-12 w-12';
    }

    if (props.size === 'lg') {
        return 'aspect-square w-full';
    }

    return 'h-16 w-16';
});

const imageObjectFitClass = computed(() => (props.size === 'lg' ? 'object-contain' : 'object-cover'));

/** @type {import('vue').ComputedRef<'sm' | 'md' | 'lg'>} */
const fallbackIconSize = computed(() => {
    if (props.size === 'sm') {
        return 'sm';
    }

    if (props.size === 'lg') {
        return 'lg';
    }

    return 'md';
});

watch(
    () => props.imageUrl,
    () => {
        broken.value = false;
    },
);

function onError() {
    broken.value = true;
}
</script>

<template>
    <div
        class="overflow-hidden rounded-xl bg-gray-100"
        :class="[
            sizeClasses,
            size !== 'lg' && 'shrink-0',
            !showImage && 'flex items-center justify-center',
        ]"
    >
        <img
            v-if="showImage"
            :src="imageUrl"
            :alt="alt"
            :class="['h-full w-full', imageObjectFitClass]"
            loading="lazy"
            @error="onError"
        >
        <EmptyStateIcon
            v-else
            name="restaurant"
            :size="fallbackIconSize"
        />
    </div>
</template>
