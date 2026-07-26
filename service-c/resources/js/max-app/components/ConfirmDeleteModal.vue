<script setup>
/**
 * Модалка подтверждения удаления сущности (блюдо, категория и т.п.).
 * Рендерится через Teleport в body поверх всего UI.
 */
defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Удалить?',
    },
    message: {
        type: String,
        default: '',
    },
    loading: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
    confirmLabel: {
        type: String,
        default: 'Удалить',
    },
});

defineEmits(['close', 'confirm']);
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 sm:items-center"
            @click.self="!loading && $emit('close')"
        >
            <div
                class="w-full max-w-lg rounded-t-2xl bg-white p-5 shadow-xl sm:rounded-2xl"
                role="dialog"
                aria-modal="true"
                aria-labelledby="confirm-delete-modal-title"
            >
                <h2 id="confirm-delete-modal-title" class="text-lg font-semibold text-gray-900">
                    {{ title }}
                </h2>
                <p v-if="message" class="mt-2 text-sm text-max-muted">
                    {{ message }}
                </p>

                <p v-if="error" class="mt-3 text-sm text-red-600">
                    {{ error }}
                </p>

                <div class="mt-5 flex gap-3">
                    <button
                        type="button"
                        class="flex-1 rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-50"
                        :disabled="loading"
                        @click="$emit('close')"
                    >
                        Отмена
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-xl bg-red-600 px-4 py-3 text-sm font-medium text-white transition hover:bg-red-700 disabled:opacity-50"
                        :disabled="loading"
                        @click="$emit('confirm')"
                    >
                        <span v-if="loading">Удаление…</span>
                        <span v-else>{{ confirmLabel }}</span>
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
