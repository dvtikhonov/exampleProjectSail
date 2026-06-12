<script setup>
import { computed, ref, watch } from 'vue';

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
        validator: (value) => ['sm', 'md'].includes(value),
    },
});

const broken = ref(false);

const showImage = computed(() => Boolean(props.imageUrl) && !broken.value);

const sizeClasses = computed(() => (props.size === 'sm' ? 'h-12 w-12' : 'h-16 w-16'));

const emojiSizeClass = computed(() => (props.size === 'sm' ? 'text-xl' : 'text-2xl'));

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
        class="shrink-0 overflow-hidden rounded-xl bg-gray-100"
        :class="[sizeClasses, !showImage && 'flex items-center justify-center', !showImage && emojiSizeClass]"
    >
        <img
            v-if="showImage"
            :src="imageUrl"
            :alt="alt"
            class="h-full w-full object-cover"
            loading="lazy"
            @error="onError"
        >
        <span v-else aria-hidden="true">🍽️</span>
    </div>
</template>
