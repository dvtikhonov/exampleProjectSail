<script setup>
/**
 * Подтверждение сохранения изменённого состава заказа перед отправкой клиенту.
 */
import { getSnapshotGroupTitle } from '../../utils/orderSnapshotGroups';

defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    groups: {
        type: Array,
        default: () => [],
    },
    itemsTotal: {
        type: String,
        default: '0.00',
    },
    loading: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
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
                aria-labelledby="composition-save-modal-title"
            >
                <h2 id="composition-save-modal-title" class="text-lg font-semibold text-gray-900">
                    Подтвердить изменения состава?
                </h2>
                <p class="mt-1 text-sm text-max-muted">
                    Клиенту будет отправлен окончательный вариант заказа.
                </p>

                <div class="mt-4 max-h-48 overflow-y-auto rounded-xl border border-gray-100 bg-gray-50 p-3">
                    <ul class="space-y-2 text-sm">
                        <li
                            v-for="group in groups"
                            :key="group.key"
                            class="flex items-center justify-between gap-3"
                        >
                            <span class="min-w-0 text-gray-700">
                                {{ getSnapshotGroupTitle(group) }} × {{ group.quantity }}
                            </span>
                            <span class="shrink-0 font-medium text-gray-900">{{ group.lineTotal }} ₽</span>
                        </li>
                    </ul>
                </div>

                <div class="mt-4 flex items-center justify-between border-t border-gray-100 pt-3 text-sm">
                    <span class="font-medium text-gray-900">Сумма блюд</span>
                    <span class="text-lg font-bold text-gray-900">{{ itemsTotal }} ₽</span>
                </div>

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
                        class="flex-1 rounded-xl bg-max-primary px-4 py-3 text-sm font-medium text-white transition hover:bg-max-primary-hover disabled:opacity-50"
                        :disabled="loading"
                        @click="$emit('confirm')"
                    >
                        <span v-if="loading">Сохранение…</span>
                        <span v-else>Подтвердить</span>
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
