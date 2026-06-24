<script setup>
/**
 * Модальное окно отклонения заказа: обязательный комментарий для клиента (до 1000 символов).
 * Рендерится через Teleport в body поверх всего UI.
 */
import { ref, watch } from 'vue';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
    title: {
        type: String,
        default: 'Отклонить заказ',
    },
});

const emit = defineEmits(['close', 'submit']);

const comment = ref('');
const localError = ref('');

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            comment.value = '';
            localError.value = '';
        }
    },
);

function handleSubmit() {
    const trimmed = comment.value.trim();

    if (trimmed === '') {
        localError.value = 'Укажите причину отклонения.';
        return;
    }

    if (trimmed.length > 1000) {
        localError.value = 'Комментарий не должен превышать 1000 символов.';
        return;
    }

    localError.value = '';
    emit('submit', trimmed);
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 sm:items-center"
            @click.self="!loading && emit('close')"
        >
            <div
                class="w-full max-w-lg rounded-t-2xl bg-white p-5 shadow-xl sm:rounded-2xl"
                role="dialog"
                aria-modal="true"
                :aria-labelledby="'reject-modal-title'"
            >
                <h2 id="reject-modal-title" class="text-lg font-semibold text-gray-900">
                    {{ title }}
                </h2>
                <p class="mt-1 text-sm text-max-muted">
                    Клиент получит сообщение с указанной причиной.
                </p>

                <textarea
                    v-model="comment"
                    rows="4"
                    maxlength="1000"
                    :disabled="loading"
                    class="mt-4 w-full resize-none rounded-xl border border-gray-200 px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-max-primary focus:outline-none focus:ring-2 focus:ring-max-primary/20 disabled:bg-gray-50"
                    placeholder="Причина отклонения…"
                />

                <p v-if="localError || error" class="mt-2 text-sm text-red-600">
                    {{ localError || error }}
                </p>

                <div class="mt-5 flex gap-3">
                    <button
                        type="button"
                        class="flex-1 rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-50"
                        :disabled="loading"
                        @click="emit('close')"
                    >
                        Отмена
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-xl bg-red-600 px-4 py-3 text-sm font-medium text-white transition hover:bg-red-700 disabled:opacity-50"
                        :disabled="loading"
                        @click="handleSubmit"
                    >
                        <span v-if="loading">Отправка…</span>
                        <span v-else>Отклонить</span>
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
