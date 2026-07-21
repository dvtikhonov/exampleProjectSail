<script setup>
/**
 * Выпадающий список с поиском для MAX mini-app.
 * Поддерживает серверный поиск через событие `search` и опции с описанием.
 */
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    options: {
        type: Array,
        default: () => [],
    },
    /** Текущая строка поиска (v-model:query) */
    query: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Выберите…',
    },
    searchPlaceholder: {
        type: String,
        default: 'Поиск…',
    },
    emptyText: {
        type: String,
        default: 'Ничего не найдено',
    },
    loading: {
        type: Boolean,
        default: false,
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
});

const emit = defineEmits(['update:modelValue', 'update:query', 'search', 'open', 'close']);

const open = ref(false);
const rootRef = ref(null);
const searchInputRef = ref(null);
const localQuery = ref(props.query);

const selectedOption = computed(
    () => props.options.find((option) => String(option.value) === String(props.modelValue)) ?? null,
);

const displayLabel = computed(() => selectedOption.value?.label ?? props.placeholder);

const isPlaceholderLabel = computed(() => !selectedOption.value);

const buttonClasses = computed(() => {
    const classes = [
        'flex w-full items-center justify-between gap-2 rounded-xl border bg-white px-3 py-2.5 text-left text-sm transition',
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

watch(
    () => props.query,
    (value) => {
        if (value !== localQuery.value) {
            localQuery.value = value;
        }
    },
);

watch(
    () => props.disabled,
    (disabled) => {
        if (disabled) {
            close();
        }
    },
);

async function openDropdown() {
    if (props.disabled || open.value) {
        return;
    }

    open.value = true;
    emit('open');
    await nextTick();
    searchInputRef.value?.focus();
}

function close() {
    if (!open.value) {
        return;
    }

    open.value = false;
    emit('close');
}

function toggle() {
    if (props.disabled) {
        return;
    }

    if (open.value) {
        close();
    } else {
        openDropdown();
    }
}

/**
 * @param {{ value: string, label: string, description?: string, disabled?: boolean }} option
 */
function selectOption(option) {
    if (option.disabled) {
        return;
    }

    emit('update:modelValue', String(option.value));
    close();
}

/**
 * @param {Event} event
 */
function onSearchInput(event) {
    const value = event.target?.value ?? '';
    localQuery.value = value;
    emit('update:query', value);
    emit('search', value);
}

/**
 * @param {MouseEvent} event
 */
function onDocumentClick(event) {
    if (!rootRef.value?.contains(event.target)) {
        close();
    }
}

/**
 * @param {KeyboardEvent} event
 */
function onDocumentKeydown(event) {
    if (event.key === 'Escape') {
        close();
    }
}

/**
 * Закрывать только при скролле вне выпадающего списка.
 *
 * @param {Event} event
 */
function onDocumentScroll(event) {
    const target = event.target;

    if (target instanceof Node && rootRef.value?.contains(target)) {
        return;
    }

    close();
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onDocumentKeydown);
    document.addEventListener('scroll', onDocumentScroll, true);
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onDocumentKeydown);
    document.removeEventListener('scroll', onDocumentScroll, true);
});
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

        <div
            v-if="open"
            class="absolute left-0 right-0 z-30 mt-1 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg"
            @click.stop
        >
            <div class="border-b border-gray-100 p-2">
                <label class="sr-only" :for="id ? `${id}-search` : undefined">Поиск</label>
                <input
                    :id="id ? `${id}-search` : undefined"
                    ref="searchInputRef"
                    type="search"
                    class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 placeholder:text-max-muted focus:border-max-primary focus:bg-white focus:outline-none focus:ring-2 focus:ring-max-primary/20"
                    :placeholder="searchPlaceholder"
                    :value="localQuery"
                    autocomplete="off"
                    @input="onSearchInput"
                >
            </div>

            <div
                v-if="loading"
                class="flex items-center justify-center gap-2 px-3 py-6 text-sm text-max-muted"
            >
                <div class="h-4 w-4 animate-spin rounded-full border-2 border-max-primary border-t-transparent" />
                Поиск…
            </div>

            <ul
                v-else-if="options.length > 0"
                role="listbox"
                class="max-h-60 overflow-y-auto py-1"
            >
                <li
                    v-for="option in options"
                    :key="`${option.value}-${option.label}`"
                    role="option"
                    :aria-selected="String(option.value) === String(modelValue)"
                    class="cursor-pointer px-3 py-2.5 text-sm transition"
                    :class="[
                        option.disabled
                            ? 'cursor-not-allowed text-max-muted'
                            : 'hover:bg-max-primary/10',
                        String(option.value) === String(modelValue) && !option.disabled
                            ? 'bg-max-primary/10 font-medium text-max-primary'
                            : 'text-gray-900',
                    ]"
                    @click.stop="selectOption(option)"
                >
                    <p class="truncate font-medium">{{ option.label }}</p>
                    <p
                        v-if="option.description"
                        class="mt-0.5 truncate text-xs text-max-muted"
                    >
                        {{ option.description }}
                    </p>
                </li>
            </ul>

            <p
                v-else
                class="px-3 py-6 text-center text-sm text-max-muted"
            >
                {{ emptyText }}
            </p>
        </div>
    </div>
</template>
