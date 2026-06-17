<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import AuthErrorAlert from '../components/AuthErrorAlert.vue';
import LoadingSpinner from '../components/LoadingSpinner.vue';
import { useOrganization } from '../composables/useOrganization';

const router = useRouter();

const {
    organization,
    isLoading,
    isResolving,
    isConfirming,
    error,
    fetchOrganization,
    resolveOrganization,
    confirmOrganization,
    resyncOrganization,
} = useOrganization();

const url = ref('');
const isEditing = ref(false);
const candidates = ref([]);
const sessionId = ref(null);
const resolvedUrl = ref(null);
const searchText = ref(null);
const clarification = ref(null);
const isInitialLoad = ref(true);

const showUrlForm = computed(() => !organization.value || isEditing.value);
const showCandidates = computed(() => showUrlForm.value && candidates.value.length > 0);
const isBusy = computed(() => isResolving.value || isConfirming.value);

const syncStatusLabels = {
    pending: 'Ожидает синхронизации',
    syncing: 'Синхронизация…',
    completed: 'Синхронизировано',
    failed: 'Ошибка синхронизации',
};

function formatRating(value) {
    if (value === null || value === undefined) {
        return '—';
    }

    return Number(value).toFixed(1);
}

function formatCount(value) {
    if (value === null || value === undefined) {
        return '—';
    }

    return value.toLocaleString('ru-RU');
}

function syncStatusLabel(status) {
    return syncStatusLabels[status] ?? status;
}

function resetResolveState() {
    candidates.value = [];
    sessionId.value = null;
    resolvedUrl.value = null;
    searchText.value = null;
    clarification.value = null;
}

function startEditing() {
    isEditing.value = true;
    url.value = organization.value?.source_url ?? '';
    resetResolveState();
}

function cancelEditing() {
    isEditing.value = false;
    url.value = '';
    resetResolveState();
}

function refineSearch() {
    resetResolveState();
}

async function onFindOrganization() {
    const trimmedUrl = url.value.trim();

    if (!trimmedUrl) {
        return;
    }

    resetResolveState();

    const result = await resolveOrganization(trimmedUrl);

    if (!result) {
        return;
    }

    sessionId.value = result.session_id;
    resolvedUrl.value = result.resolved_url;
    searchText.value = result.search_text ?? null;
    clarification.value = result.clarification ?? null;
    candidates.value = result.candidates ?? [];

    if (result.auto_selected && candidates.value.length === 1) {
        await selectCandidate(candidates.value[0]);
    }
}

async function selectCandidate(candidate) {
    if (!sessionId.value) {
        return;
    }

    const saved = await confirmOrganization(sessionId.value, candidate.org_id);

    if (!saved) {
        return;
    }

    isEditing.value = false;
    resetResolveState();
    await router.push({ name: 'reviews' });
}

async function onResyncReviews() {
    const saved = await resyncOrganization();

    if (saved) {
        await router.push({ name: 'reviews' });
    }
}

onMounted(async () => {
    await fetchOrganization();
    isInitialLoad.value = false;
});
</script>

<template>
    <div class="flex min-h-dvh items-start justify-center px-4 py-10">
        <div class="w-full max-w-2xl rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-medium uppercase tracking-wide text-sky-700">
                        Настройки
                    </p>
                    <h1 class="mt-1 text-2xl font-semibold text-slate-900">
                        Организация на Яндекс.Картах
                    </h1>
                </div>
                <router-link
                    v-if="organization"
                    :to="{ name: 'reviews' }"
                    class="text-sm font-medium text-sky-700 hover:underline"
                >
                    К отзывам →
                </router-link>
            </div>

            <div
                v-if="isInitialLoad"
                class="mt-10 flex justify-center py-8"
            >
                <LoadingSpinner label="Загрузка настроек…" />
            </div>

            <template v-else>
                <div
                    v-if="organization && !isEditing"
                    class="mt-8 rounded-xl border border-slate-200 bg-slate-50 p-5"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">
                                {{ organization.name }}
                            </h2>
                            <p class="mt-1 text-sm text-slate-600">
                                {{ organization.address }}
                            </p>
                            <p class="mt-2 text-xs text-slate-500">
                                ID: {{ organization.yandex_org_id }}
                            </p>
                        </div>
                        <div class="text-right text-sm text-slate-600">
                            <p>
                                Рейтинг:
                                <span class="font-medium text-slate-900">{{ formatRating(organization.average_rating) }}</span>
                            </p>
                            <p class="mt-1">
                                {{ formatCount(organization.ratings_count) }} оценок ·
                                {{ formatCount(organization.reviews_count) }} отзывов
                            </p>
                        </div>
                    </div>

                    <p class="mt-4 text-sm text-slate-500">
                        Статус:
                        <span
                            class="font-medium"
                            :class="organization.sync_status === 'failed' ? 'text-red-700' : 'text-slate-700'"
                        >
                            {{ syncStatusLabel(organization.sync_status) }}
                        </span>
                    </p>
                    <p
                        v-if="organization.sync_error"
                        class="mt-2 text-sm text-red-700"
                    >
                        {{ organization.sync_error }}
                    </p>

                    <div class="mt-5 flex flex-wrap gap-3">
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                            @click="startEditing"
                        >
                            Изменить ссылку
                        </button>
                        <button
                            type="button"
                            :disabled="isLoading"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-60"
                            @click="onResyncReviews"
                        >
                            <span
                                v-if="isLoading"
                                class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
                                aria-hidden="true"
                            />
                            <span>{{ isLoading ? 'Запуск…' : 'Обновить отзывы' }}</span>
                        </button>
                    </div>
                </div>

                <div
                    v-if="showUrlForm && !showCandidates"
                    class="mt-8"
                >
                    <form
                        class="space-y-4"
                        @submit.prevent="onFindOrganization"
                    >
                        <div>
                            <label
                                class="mb-1 block text-sm font-medium text-slate-700"
                                for="organization-search"
                            >
                                Ссылка и уточнение
                            </label>
                            <input
                                id="organization-search"
                                v-model="url"
                                type="text"
                                required
                                placeholder="www.invitro.ru Новокузнецк"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-sky-500 focus:ring-2"
                            >
                            <p class="mt-2 text-xs text-slate-500">
                                Сначала укажите ссылку на сайт или Яндекс.Карты, затем через пробел — уточнение
                                (город, адрес, филиал). Пример:
                                <code class="rounded bg-slate-100 px-1">www.invitro.ru Новокузнецк</code>
                                или
                                <code class="rounded bg-slate-100 px-1">https://yandex.ru/maps/...</code>.
                            </p>
                        </div>

                        <AuthErrorAlert :message="error" />

                        <div class="flex flex-wrap gap-3">
                            <button
                                type="submit"
                                :disabled="isBusy"
                                class="inline-flex items-center justify-center gap-2 rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <span
                                    v-if="isResolving"
                                    class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
                                    aria-hidden="true"
                                />
                                <span>{{ isResolving ? 'Поиск…' : 'Найти организацию' }}</span>
                            </button>
                            <button
                                v-if="isEditing"
                                type="button"
                                class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                                @click="cancelEditing"
                            >
                                Отмена
                            </button>
                        </div>
                    </form>
                </div>

                <div
                    v-if="showCandidates"
                    class="mt-8 space-y-4"
                >
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
                                        {{ formatCount(candidate.ratings_count) }} оценок ·
                                        {{ formatCount(candidate.reviews_count) }} отзывов
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    :disabled="isBusy"
                                    class="shrink-0 rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-60"
                                    @click="selectCandidate(candidate)"
                                >
                                    {{ isConfirming ? 'Сохранение…' : 'Выбрать' }}
                                </button>
                            </div>
                        </li>
                    </ul>

                    <button
                        type="button"
                        class="text-sm font-medium text-sky-700 hover:underline"
                        @click="refineSearch"
                    >
                        ← Уточнить поиск
                    </button>
                </div>

                <div
                    v-if="isConfirming && !showCandidates"
                    class="mt-6 flex justify-center py-4"
                >
                    <LoadingSpinner label="Сохранение организации…" />
                </div>
            </template>
        </div>
    </div>
</template>
