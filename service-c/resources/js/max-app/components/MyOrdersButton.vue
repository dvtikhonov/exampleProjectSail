<script setup>
/**
 * Кнопка перехода к списку заказов с бейджем непрочитанных сообщений.
 */
import { computed } from 'vue';

const props = defineProps({
    label: {
        type: String,
        default: 'Мои заказы',
    },
    unreadCount: {
        type: Number,
        default: 0,
    },
    buttonClass: {
        type: String,
        default:
            'rounded-full px-3 py-2 text-sm font-medium text-max-primary transition hover:bg-max-primary/10',
    },
});

defineEmits(['click']);

const displayCount = computed(() => (props.unreadCount > 99 ? '99+' : String(props.unreadCount)));

const hintText = computed(() => `${props.unreadCount} ${pluralizeMessages(props.unreadCount)}`);

/**
 * @param {number} count
 */
function pluralizeMessages(count) {
    const mod10 = count % 10;
    const mod100 = count % 100;

    if (mod10 === 1 && mod100 !== 11) {
        return 'новое сообщение';
    }

    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) {
        return 'новых сообщения';
    }

    return 'новых сообщений';
}
</script>

<template>
    <div class="relative">
        <button
            type="button"
            :class="buttonClass"
            :aria-label="unreadCount > 0 ? `${label}, ${hintText}` : label"
            @click="$emit('click')"
        >
            <span class="relative inline-flex items-center">
                {{ label }}
                <span
                    v-if="unreadCount > 0"
                    class="group/badge absolute -right-3 -top-2 flex h-4 min-w-4 cursor-default items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold leading-none text-white"
                    :aria-label="hintText"
                >
                    {{ displayCount }}
                    <span
                        class="pointer-events-none absolute left-1/2 top-full z-20 mt-1.5 -translate-x-1/2 whitespace-nowrap rounded-xl bg-gray-900 px-3 py-1.5 text-xs font-medium text-white opacity-0 shadow-lg transition-opacity duration-150 group-hover/badge:opacity-100"
                        role="tooltip"
                    >
                        {{ hintText }}
                        <span
                            class="absolute -top-1 left-1/2 h-2 w-2 -translate-x-1/2 rotate-45 bg-gray-900"
                            aria-hidden="true"
                        />
                    </span>
                </span>
            </span>
        </button>
    </div>
</template>
