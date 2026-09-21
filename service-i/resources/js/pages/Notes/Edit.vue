<script setup>
/**
 * Форма создания (/notes/new) и редактирования (/notes/:id/edit).
 */
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useNotesStore } from '../../stores/notes';

const props = defineProps({
    id: {
        type: [String, Number],
        default: null,
    },
});

const route = useRoute();
const router = useRouter();
const store = useNotesStore();
const { loading, error } = storeToRefs(store);

const isCreate = computed(() => route.name === 'notes.create' || !props.id);

const form = reactive({
    title: '',
    content: '',
    tagsText: '',
    archived: false,
});

const fieldErrors = ref({});
const saving = ref(false);

function tagsFromText(text) {
    return text
        .split(',')
        .map((t) => t.trim())
        .filter(Boolean);
}

function fillFromNote(note) {
    form.title = note.title ?? '';
    form.content = note.content ?? '';
    form.tagsText = Array.isArray(note.tags) ? note.tags.join(', ') : '';
    form.archived = Boolean(note.archived);
}

async function load() {
    fieldErrors.value = {};
    store.clearError();

    if (isCreate.value) {
        form.title = '';
        form.content = '';
        form.tagsText = '';
        form.archived = false;
        return;
    }

    try {
        const note = await store.loadOne(props.id);
        fillFromNote(note);
    } catch {
        // store.error
    }
}

onMounted(load);
watch(() => [props.id, route.name], load);

async function onSubmit() {
    fieldErrors.value = {};
    store.clearError();
    saving.value = true;

    const payload = {
        title: form.title,
        content: form.content || null,
        tags: tagsFromText(form.tagsText),
        archived: form.archived,
    };

    try {
        if (isCreate.value) {
            const note = await store.create(payload);
            await router.push(`/notes/${note.id}/edit`);
        } else {
            await store.update(props.id, payload);
        }
    } catch (e) {
        const errors = e?.response?.data?.errors;
        if (errors && typeof errors === 'object') {
            fieldErrors.value = errors;
        }
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <section>
        <div class="mb-6">
            <h1 class="text-2xl font-semibold tracking-tight">
                {{ isCreate ? 'Новая заметка' : 'Редактирование' }}
            </h1>
            <p class="mt-1 text-sm text-muted">
                {{ isCreate ? 'Заполните заголовок и сохраните.' : `ID ${id}` }}
            </p>
        </div>

        <p
            v-if="error"
            class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800"
            role="alert"
        >
            {{ error }}
        </p>

        <form
            class="space-y-5"
            @submit.prevent="onSubmit"
        >
            <div>
                <label
                    for="note-title"
                    class="mb-1 block text-sm font-medium"
                >Заголовок</label>
                <input
                    id="note-title"
                    v-model="form.title"
                    type="text"
                    maxlength="255"
                    required
                    class="w-full rounded-md border border-line bg-white px-3 py-2 text-sm outline-none focus:border-accent"
                    autocomplete="off"
                >
                <p
                    v-if="fieldErrors.title"
                    class="mt-1 text-xs text-red-700"
                >
                    {{ fieldErrors.title[0] }}
                </p>
            </div>

            <div>
                <label
                    for="note-content"
                    class="mb-1 block text-sm font-medium"
                >Содержимое</label>
                <textarea
                    id="note-content"
                    v-model="form.content"
                    rows="8"
                    class="w-full rounded-md border border-line bg-white px-3 py-2 text-sm outline-none focus:border-accent"
                />
            </div>

            <div>
                <label
                    for="note-tags"
                    class="mb-1 block text-sm font-medium"
                >Теги</label>
                <input
                    id="note-tags"
                    v-model="form.tagsText"
                    type="text"
                    placeholder="через запятую"
                    class="w-full rounded-md border border-line bg-white px-3 py-2 text-sm outline-none focus:border-accent"
                >
                <p class="mt-1 text-xs text-muted">Например: work, ideas</p>
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input
                    v-model="form.archived"
                    type="checkbox"
                    class="rounded border-line text-accent focus:ring-accent"
                >
                В архиве
            </label>

            <div class="flex flex-wrap items-center gap-3 pt-2">
                <button
                    type="submit"
                    class="rounded-md bg-accent px-4 py-2 text-sm font-medium text-white transition hover:bg-accent-hover disabled:opacity-60"
                    :disabled="saving || loading"
                >
                    {{ saving ? 'Сохранение…' : 'Сохранить' }}
                </button>
                <RouterLink
                    to="/notes"
                    class="text-sm text-muted hover:text-ink"
                >
                    К списку
                </RouterLink>
            </div>
        </form>
    </section>
</template>
