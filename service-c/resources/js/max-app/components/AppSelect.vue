<script setup>
/**
 * Кастомный select для MAX mini-app: нативный <select> в QtWebEngine (desktop)
 * раздувает белый фон при открытии списка.
 */
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    options: {
        type: Array,
        default: () => [],
    },
    placeholder: {
        type: String,
        default: 'Выберите…',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    invalid: {
        type: Boolean,
        default: false,
    },
    id: {
        type: String,
        default: undefined,
    },
    size: {
        type: String,
        default: 'md',
        validator: (value) => ['sm', 'md'].includes(value),
    },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const rootRef = ref(null);

const sizeClasses = computed(() => (props.size === 'sm' ? 'px-3 py-2 text-sm' : 'px-3 py-2.5 text-sm'));

const selectedOption = computed(() => props.options.find((option) => option.value === props.modelValue) ?? null);

const displayLabel = computed(() => {
    if (selectedOption.value) {
        return selectedOption.value.label;
    }

    const emptyOption = props.options.find((option) => option.value === '' && !option.disabled);

    if (props.modelValue === '' && emptyOption) {
        return emptyOption.label;
    }

    return props.placeholder;
});

const isPlaceholderLabel = computed(() => {
    if (!selectedOption.value) {
        return props.modelValue === '';
    }

    return Boolean(selectedOption.value.disabled);
});

const buttonClasses = computed(() => {
    const classes = [
        'flex w-full items-center justify-between gap-2 rounded-xl border bg-white text-left transition',
        sizeClasses.value,
    ];

    if (props.invalid) {
        classes.push('border-red-300');
    } else if (open.value) {
        classes.push('border-max-primary ring-1 ring-max-primary');
    } else {
        classes.push('border-gray-200');
    }

    if (props.disabled) {
        classes.push('cursor-not-allowed bg-gray-50 text-gray-400');
    } else {
        classes.push('cursor-pointer hover:border-max-primary/40');
    }

    return classes;
});

function toggle() {
    if (props.disabled) {
        return;
    }

    open.value = !open.value;
}

/**
 * @param {{ value: string, label: string, disabled?: boolean }} option
 */
function selectOption(option) {
    if (option.disabled) {
        return;
    }

    emit('update:modelValue', option.value);
    open.value = false;
}

function close() {
    open.value = false;
}

/**
 * @param {MouseEvent} event
 */
function onDocumentClick(event) {
    if (!rootRef.value?.contains(event.target)) {
        close();
    }
}

function onDocumentKeydown(event) {
    if (event.key === 'Escape') {
        close();
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onDocumentKeydown);
    document.addEventListener('scroll', close, true);
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onDocumentKeydown);
    document.removeEventListener('scroll', close, true);
});

watch(
    () => props.disabled,
    (disabled) => {
        if (disabled) {
            close();
        }
    },
);
</script>

<template>
    <div ref="rootRef" class="relative">
        <button
            :id="id"
            type="button"
            :class="buttonClasses"
            :disabled="disabled"
            :aria-expanded="open"
            aria-haspopup="listbox"
            @click.stop="toggle"
        >
            <span
                class="min-w-0 truncate"
                :class="isPlaceholderLabel ? 'text-max-muted' : 'text-gray-900'"
            >
                {{ displayLabel }}
            </span>
            <svg
                class="h-4 w-4 shrink-0 text-gray-500 transition"
                :class="open ? 'rotate-180' : ''"
                viewBox="0 0 20 20"
                fill="currentColor"
                aria-hidden="true"
            >
                <path
                    fill-rule="evenodd"
                    d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z"
                    clip-rule="evenodd"
                />
            </svg>
        </button>

        <ul
            v-if="open"
            role="listbox"
            class="absolute left-0 right-0 z-30 mt-1 max-h-60 overflow-y-auto rounded-xl border border-gray-200 bg-white py-1 shadow-lg"
        >
            <li
                v-for="option in options"
                :key="`${option.value}-${option.label}`"
                role="option"
                :aria-selected="option.value === modelValue"
                class="px-3 py-2 text-sm transition"
                :class="[
                    option.disabled
                        ? 'cursor-not-allowed text-max-muted'
                        : 'cursor-pointer hover:bg-max-primary/10',
                    option.value === modelValue && !option.disabled
                        ? 'bg-max-primary/10 font-medium text-max-primary'
                        : 'text-gray-900',
                ]"
                @click.stop="selectOption(option)"
            >
                {{ option.label }}
            </li>
        </ul>
    </div>
</template>
