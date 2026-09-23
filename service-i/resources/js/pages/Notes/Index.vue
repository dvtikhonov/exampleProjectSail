<script setup>
/**
 * Список заметок: фильтры, URL sync, «Загрузить ещё».
 *
 * onMounted: URL → store → один loadFirst().
 * watch фильтров без immediate (нет двойного loadFirst).
 * Debounce q (~300 ms) только здесь, не в Pinia.
 */
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import NoteCard from '../../components/NoteCard.vue';
import { useNotesStore } from '../../stores/notes';

const route = useRoute();
const router = useRouter();
const store = useNotesStore();

const {
    items,
    total,
    limit,
    loading,
    error,
    q,
    tags,
    archived,
    sort,
    hasMore,
} = storeToRefs(store);

/** Локальный текст тегов; в store пишем на change (не на каждый символ). */
const tagsInput = ref('');

/** @type {ReturnType<typeof setTimeout>|null} */
let qDebounceTimer = null;

/**
 * @param {unknown} value
 * @returns {string|undefined}
 */
function queryScalar(value) {
    if (Array.isArray(value)) {
        return value[0] !== undefined ? String(value[0]) : undefined;
    }

    if (value === undefined || value === null) {
        return undefined;
    }

    return String(value);
}

/**
 * Читает route.query в store (defaults как у API).
 * offset из URL игнорируется.
 */
function applyQueryToStore() {
    const query = route.query;

    const qRaw = queryScalar(query.q);
    q.value = qRaw ?? '';

    const tagsRaw = query.tags;
    if (Array.isArray(tagsRaw)) {
        tags.value = tagsRaw
            .map((t) => String(t).trim())
            .filter((t) => t !== '');
    } else if (typeof tagsRaw === 'string' && tagsRaw.trim() !== '') {
        tags.value = tagsRaw
            .split(',')
            .map((t) => t.trim())
            .filter((t) => t !== '');
    } else {
        tags.value = [];
    }
    tagsInput.value = tags.value.join(', ');

    // Невалидные значения (например archived=maybe) оставляем — API вернёт 422.
    const archivedRaw = queryScalar(query.archived);
    archived.value = archivedRaw !== undefined && archivedRaw !== ''
        ? archivedRaw
        : 'false';

    const sortRaw = queryScalar(query.sort);
    sort.value = sortRaw && sortRaw !== '' ? sortRaw : '-updated_at';

    const limitRaw = queryScalar(query.limit);
    if (limitRaw !== undefined && limitRaw !== '') {
        const parsed = Number.parseInt(limitRaw, 10);
        limit.value = Number.isFinite(parsed) ? parsed : 20;
    } else {
        limit.value = 20;
    }
}

/**
 * Пишет в URL только недефолтные значения; offset никогда.
 */
function syncQueryFromStore() {
    const query = { ...route.query };

    const trimmedQ = q.value.trim();
    if (trimmedQ) {
        query.q = trimmedQ;
    } else {
        delete query.q;
    }

    if (tags.value.length) {
        query.tags = tags.value.join(',');
    } else {
        delete query.tags;
    }

    if (archived.value !== 'false') {
        query.archived = archived.value;
    } else {
        delete query.archived;
    }

    if (sort.value !== '-updated_at') {
        query.sort = sort.value;
    } else {
        delete query.sort;
    }

    if (limit.value !== 20) {
        query.limit = String(limit.value);
    } else {
        delete query.limit;
    }

    delete query.offset;

    router.replace({ query });
}

function syncAndReload() {
    syncQueryFromStore();
    store.loadFirst().catch(() => {
        // ошибка уже в store.error
    });
}

function applyTagsFromInput() {
    tags.value = tagsInput.value
        .split(',')
        .map((t) => t.trim())
        .filter((t) => t !== '');
}

onMounted(() => {
    applyQueryToStore();
    store.loadFirst().catch(() => {
        // ошибка уже в store.error
    });
});

onBeforeUnmount(() => {
    if (qDebounceTimer !== null) {
        clearTimeout(qDebounceTimer);
    }
});

// Без immediate: на mount срабатывает только явный loadFirst выше.
watch(q, () => {
    if (qDebounceTimer !== null) {
        clearTimeout(qDebounceTimer);
    }
    qDebounceTimer = setTimeout(() => {
        qDebounceTimer = null;
        syncAndReload();
    }, 300);
});

watch(
    [archived, sort, limit, tags],
    () => {
        syncAndReload();
    },
    { deep: true },
);

async function onDelete(note) {
    if (!window.confirm(`Удалить «${note.title}»?`)) {
        return;
    }

    try {
        await store.remove(note.id);
        await store.loadFirst();
    } catch {
        // ошибка уже в store.error
    }
}

function onLoadMore() {
    store.loadMore().catch(() => {
        // ошибка уже в store.error; items/total сохранены
    });
}
</script>

<template>
    <section>
        <div class="mb-6">
            <h1 class="text-2xl font-semibold tracking-tight">Заметки</h1>
            <p class="mt-1 text-sm text-muted">
                Поиск, фильтры и постраничная загрузка. Параметры — в URL.
            </p>
        </div>

        <form
            class="mb-6 grid gap-3 sm:grid-cols-2"
            @submit.prevent
        >
            <div class="sm:col-span-2">
                <label
                    for="notes-q"
                    class="mb-1 block text-sm font-medium"
                >Поиск</label>
                <input
                    id="notes-q"
                    v-model="q"
                    type="search"
                    maxlength="255"
                    placeholder="Заголовок или содержимое"
                    class="w-full rounded-md border border-line bg-white px-3 py-2 text-sm outline-none focus:border-accent"
                    autocomplete="off"
                >
            </div>

            <div>
                <label
                    for="notes-tags"
                    class="mb-1 block text-sm font-medium"
                >Теги</label>
                <input
                    id="notes-tags"
                    v-model="tagsInput"
                    type="text"
                    placeholder="work, urgent"
                    class="w-full rounded-md border border-line bg-white px-3 py-2 text-sm outline-none focus:border-accent"
                    autocomplete="off"
                    @change="applyTagsFromInput"
                >
            </div>

            <div>
                <label
                    for="notes-archived"
                    class="mb-1 block text-sm font-medium"
                >Архив</label>
                <select
                    id="notes-archived"
                    v-model="archived"
                    class="w-full rounded-md border border-line bg-white px-3 py-2 text-sm outline-none focus:border-accent"
                >
                    <option value="false">Активные</option>
                    <option value="true">В архиве</option>
                    <option value="all">Все</option>
                </select>
            </div>

            <div>
                <label
                    for="notes-sort"
                    class="mb-1 block text-sm font-medium"
                >Сортировка</label>
                <select
                    id="notes-sort"
                    v-model="sort"
                    class="w-full rounded-md border border-line bg-white px-3 py-2 text-sm outline-none focus:border-accent"
                >
                    <option value="-updated_at">Сначала обновлённые</option>
                    <option value="updated_at">Сначала старые обновления</option>
                    <option value="-created_at">Сначала новые</option>
                    <option value="created_at">Сначала старые</option>
                    <option value="title">Заголовок A→Z</option>
                    <option value="-title">Заголовок Z→A</option>
                </select>
            </div>

            <div>
                <label
                    for="notes-limit"
                    class="mb-1 block text-sm font-medium"
                >На странице</label>
                <select
                    id="notes-limit"
                    v-model.number="limit"
                    class="w-full rounded-md border border-line bg-white px-3 py-2 text-sm outline-none focus:border-accent"
                >
                    <option :value="10">10</option>
                    <option :value="20">20</option>
                    <option :value="50">50</option>
                    <option :value="100">100</option>
                </select>
            </div>
        </form>

        <p
            v-if="error"
            class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800"
            role="alert"
        >
            {{ error }}
        </p>

        <p
            v-if="!error"
            class="mb-3 text-sm text-muted"
        >
            Показано {{ items.length }} из {{ total }}
        </p>

        <p
            v-if="loading && !items.length"
            class="text-sm text-muted"
        >
            Загрузка…
        </p>

        <p
            v-else-if="!loading && !items.length"
            class="text-sm text-muted"
        >
            {{ error ? 'Список пуст из‑за ошибки фильтра или запроса.' : 'Пока нет заметок. Создайте первую.' }}
        </p>

        <div
            v-else-if="items.length"
            class="rounded-lg border border-line bg-white px-4 sm:px-5"
        >
            <NoteCard
                v-for="note in items"
                :key="note.id"
                :note="note"
                @delete="onDelete"
            />
        </div>

        <div
            v-if="items.length"
            class="mt-4"
        >
            <button
                type="button"
                class="rounded-md border border-line bg-white px-4 py-2 text-sm font-medium text-ink transition hover:border-accent hover:text-accent disabled:opacity-60"
                :disabled="loading || !hasMore"
                @click="onLoadMore"
            >
                {{ loading ? 'Загрузка…' : (hasMore ? 'Загрузить ещё' : 'Больше нет') }}
            </button>
        </div>
    </section>
</template>
