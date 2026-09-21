<script setup>
/**
 * Список заметок без фильтров / пагинации / sync query.
 */
import { onMounted } from 'vue';
import { storeToRefs } from 'pinia';
import NoteCard from '../../components/NoteCard.vue';
import { useNotesStore } from '../../stores/notes';

const store = useNotesStore();
const { items, loading, error } = storeToRefs(store);

onMounted(() => {
    store.loadAll();
});

async function onDelete(note) {
    if (!window.confirm(`Удалить «${note.title}»?`)) {
        return;
    }

    try {
        await store.remove(note.id);
    } catch {
        // ошибка уже в store.error
    }
}
</script>

<template>
    <section>
        <div class="mb-6">
            <h1 class="text-2xl font-semibold tracking-tight">Заметки</h1>
            <p class="mt-1 text-sm text-muted">
                Публичный список без фильтров и пагинации.
            </p>
        </div>

        <p
            v-if="error"
            class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800"
            role="alert"
        >
            {{ error }}
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
            Пока нет заметок. Создайте первую.
        </p>

        <div
            v-else
            class="rounded-lg border border-line bg-white px-4 sm:px-5"
        >
            <NoteCard
                v-for="note in items"
                :key="note.id"
                :note="note"
                @delete="onDelete"
            />
        </div>
    </section>
</template>
