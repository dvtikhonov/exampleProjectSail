<script setup>
/**
 * Список кандидатов после POST /organization/resolve.
 *
 * Показывается, когда парсер нашёл несколько организаций по одной ссылке.
 * select — выбор кандидата (confirm на бэкенде); refine — вернуться к форме URL.
 */
import AuthErrorAlert from '../AuthErrorAlert.vue';
import { formatCount, formatRating } from '../../utils/formatters';

defineProps({
    candidates: {
        type: Array,
        required: true,
    },
    searchText: {
        type: String,
        default: null,
    },
    clarification: {
        type: String,
        default: null,
    },
    isBusy: {
        type: Boolean,
        default: false,
    },
    isConfirming: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: null,
    },
});

defineEmits(['select', 'refine']);
</script>

<template>
    <div class="mt-8 space-y-4">
        <!-- Подсказка от API: сколько кандидатов и текст уточнения -->
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Найдено {{ candidates.length }} организаций. Выберите нужную или уточните ссылку.
            <span
                v-if="searchText"
                class="mt-1 block text-xs text-amber-800/80"
            >
                Поисковый запрос: {{ searchText }}
            </span>
            <span
                v-if="clarification"
                class="mt-1 block text-xs text-amber-800/80"
            >
                Адрес / уточнение: {{ clarification }}
            </span>
        </div>

        <AuthErrorAlert :message="error" />

        <!-- Карточки кандидатов: имя, адрес, рейтинг, кнопка «Выбрать» -->
        <ul class="space-y-3">
            <li
                v-for="candidate in candidates"
                :key="candidate.org_id"
                class="rounded-xl border border-slate-200 p-4 transition hover:border-sky-200 hover:bg-sky-50/40"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <h3 class="font-semibold text-slate-900">
                            {{ candidate.name }}
                        </h3>
                        <p
                            v-if="candidate.address"
                            class="mt-1 text-sm text-slate-600"
                        >
                            {{ candidate.address }}
                        </p>
                        <p
                            v-else-if="clarification"
                            class="mt-1 text-sm italic text-slate-500"
                        >
                            Адрес не указан в карточке · уточнение: {{ clarification }}
                        </p>
                        <p class="mt-2 text-xs text-slate-500">
                            ID: {{ candidate.org_id }}
                        </p>
                        <p class="mt-1 text-sm text-slate-600">
                            Рейтинг {{ formatRating(candidate.average_rating) }} ·
                            {{ formatCount(candidate.ratings_count) }} оценок
                        </p>
                    </div>
                    <button
                        type="button"
                        :disabled="isBusy"
                        class="shrink-0 rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-60"
                        @click="$emit('select', candidate)"
                    >
                        {{ isConfirming ? 'Сохранение…' : 'Выбрать' }}
                    </button>
                </div>
            </li>
        </ul>

        <!-- Сброс resolve-сессии и возврат к форме URL -->
        <button
            type="button"
            class="text-sm font-medium text-sky-700 hover:underline"
            @click="$emit('refine')"
        >
            ← Уточнить поиск
        </button>
    </div>
</template>
