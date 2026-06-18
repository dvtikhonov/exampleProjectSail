<script setup>
/**
 * Страница настроек организации на Яндекс.Картах.
 *
 * Сценарии:
 * 1. Организация уже привязана — показываем сводку (OrganizationSummary).
 * 2. Организации нет или пользователь нажал «Изменить» — форма поиска по URL.
 * 3. API вернул несколько кандидатов — выбор из списка (OrganizationCandidates).
 * 4. После подтверждения или ресинка — переход на страницу отзывов.
 */
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AuthErrorAlert from '../components/AuthErrorAlert.vue';
import LoadingSpinner from '../components/LoadingSpinner.vue';
import PageHeader from '../components/layout/PageHeader.vue';
import PageShell from '../components/layout/PageShell.vue';
import OrganizationCandidates from '../components/settings/OrganizationCandidates.vue';
import OrganizationSearchForm from '../components/settings/OrganizationSearchForm.vue';
import OrganizationSummary from '../components/settings/OrganizationSummary.vue';
import PrimaryButton from '../components/ui/PrimaryButton.vue';
import { useAuth } from '../composables/useAuth';
import { useOrganization } from '../composables/useOrganization';

const route = useRoute();
const router = useRouter();
const { logout, isLoading: isLoggingOut, error: authError } = useAuth();

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

/** URL из формы поиска (новая привязка или редактирование). */
const url = ref('');
/** Режим смены уже привязанной организации. */
const isEditing = ref(false);

/** Состояние двухшагового resolve → confirm (сбрасывается через resetResolveState). */
const candidates = ref([]);
const sessionId = ref(null);
const resolvedUrl = ref(null);
const searchText = ref(null);
const clarification = ref(null);

/** Первичная загрузка при монтировании / смене organizationId в маршруте. */
const isInitialLoad = ref(true);

/** ID организации из URL; null — если параметр отсутствует или не число. */
const organizationId = computed(() => {
    const raw = route.params.organizationId;

    if (!raw) {
        return null;
    }

    const id = Number(raw);

    return Number.isNaN(id) ? null : id;
});

/** Форма URL: нет привязки или пользователь редактирует существующую. */
const showUrlForm = computed(() => !organization.value || isEditing.value);
/** Список кандидатов показываем вместо формы, пока resolve не завершён выбором. */
const showCandidates = computed(() => showUrlForm.value && candidates.value.length > 0);
const isBusy = computed(() => isResolving.value || isConfirming.value);

/** Ссылка в шапке на отзывы — только если организация уже загружена. */
const reviewsLink = computed(() => {
    if (!organization.value) {
        return null;
    }

    return {
        name: 'reviews',
        params: { organizationId: organization.value.id },
        label: 'К отзывам →',
    };
});

/** Очищает промежуточный результат resolve (кандидаты, сессия, подсказки API). */
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

/** Возврат к форме URL без перезагрузки страницы («Уточнить поиск»). */
function refineSearch() {
    resetResolveState();
}

/** POST /organization/resolve — по URL получаем кандидатов или авто-выбор. */
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

    // Бэкенд однозначно определил организацию — подтверждаем без показа списка.
    if (result.auto_selected && candidates.value.length === 1) {
        await selectCandidate(candidates.value[0]);
    }
}

/** POST /organization/confirm — сохраняет выбор и ведёт на отзывы. */
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
    await router.push({ name: 'reviews', params: { organizationId: saved.id } });
}

/** Повторная синхронизация метаданных организации с Яндекс.Картами. */
async function onResyncReviews() {
    if (!organization.value?.id) {
        return;
    }

    const saved = await resyncOrganization(organization.value.id);

    if (saved) {
        await router.push({ name: 'reviews', params: { organizationId: saved.id } });
    }
}

async function loadSettings() {
    await fetchOrganization(organizationId.value);
}

/** POST /logout и переход на login при успехе. */
async function onLogout() {
    const ok = await logout();

    if (ok) {
        await router.push({ name: 'login' });
    }
}

// При навигации между организациями перезагружаем данные с полноэкранным спиннером.
watch(organizationId, async () => {
    isInitialLoad.value = true;
    await loadSettings();
    isInitialLoad.value = false;
});

onMounted(async () => {
    await loadSettings();
    isInitialLoad.value = false;
});
</script>

<template>
    <PageShell>
        <!-- Шапка: переход к отзывам доступен только при привязанной организации -->
        <PageHeader
            eyebrow="Настройки"
            title="Организация на Яндекс.Картах"
        >
            <template #actions>
                <div class="flex flex-wrap items-center gap-3">
                    <router-link
                        v-if="reviewsLink"
                        :to="{ name: reviewsLink.name, params: reviewsLink.params }"
                        class="text-sm font-medium text-sky-700 hover:underline"
                    >
                        {{ reviewsLink.label }}
                    </router-link>
                    <PrimaryButton
                        variant="secondary"
                        :loading="isLoggingOut"
                        @click="onLogout"
                    >
                        {{ isLoggingOut ? 'Выход…' : 'Выйти' }}
                    </PrimaryButton>
                </div>
            </template>
        </PageHeader>

        <AuthErrorAlert
            v-if="authError"
            :message="authError"
            class="mt-4"
        />

        <!-- Первичная загрузка / смена organizationId в маршруте -->
        <div
            v-if="isInitialLoad"
            class="mt-10 flex justify-center py-8"
        >
            <LoadingSpinner label="Загрузка настроек…" />
        </div>

        <template v-else>
            <!-- Режим просмотра: карточка привязанной организации -->
            <OrganizationSummary
                v-if="organization && !isEditing"
                :organization="organization"
                :is-loading="isLoading"
                @edit="startEditing"
                @resync="onResyncReviews"
            />

            <!-- Поиск по URL: первая привязка или редактирование -->
            <OrganizationSearchForm
                v-if="showUrlForm && !showCandidates"
                v-model:url="url"
                :is-resolving="isResolving"
                :is-editing="isEditing"
                :error="error"
                :is-busy="isBusy"
                @submit="onFindOrganization"
                @cancel="cancelEditing"
            />

            <!-- Несколько совпадений после resolve — пользователь выбирает вручную -->
            <OrganizationCandidates
                v-if="showCandidates"
                :candidates="candidates"
                :search-text="searchText"
                :clarification="clarification"
                :is-busy="isBusy"
                :is-confirming="isConfirming"
                :error="error"
                @select="selectCandidate"
                @refine="refineSearch"
            />

            <!-- Авто-выбор одного кандидата: confirm идёт без списка, нужен отдельный спиннер -->
            <div
                v-if="isConfirming && !showCandidates"
                class="mt-6 flex justify-center py-4"
            >
                <LoadingSpinner label="Сохранение организации…" />
            </div>
        </template>
    </PageShell>
</template>
