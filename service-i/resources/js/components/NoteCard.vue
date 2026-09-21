<script setup>
/**
 * Карточка заметки: заголовок, excerpt, теги, удаление.
 */
import { computed } from 'vue';
import { RouterLink } from 'vue-router';

const props = defineProps({
    note: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['delete']);

const excerpt = computed(() => {
    const text = (props.note.content ?? '').trim();
    if (!text) {
        return 'Без содержимого';
    }

    return text.length > 140 ? `${text.slice(0, 140)}…` : text;
});

const tags = computed(() => (Array.isArray(props.note.tags) ? props.note.tags : []));
</script>

<template>
    <article class="border-b border-line py-5 first:pt-0 last:border-b-0 last:pb-0">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0 flex-1">
                <RouterLink
                    :to="`/notes/${note.id}/edit`"
                    class="block truncate text-base font-semibold text-ink hover:text-accent"
                >
                    {{ note.title }}
                </RouterLink>
                <p class="mt-1 text-sm leading-relaxed text-muted">
                    {{ excerpt }}
                </p>
                <ul
                    v-if="tags.length"
                    class="mt-3 flex flex-wrap gap-2"
                >
                    <li
                        v-for="tag in tags"
                        :key="tag"
                        class="text-xs text-accent"
                    >
                        #{{ tag }}
                    </li>
                </ul>
                <p
                    v-if="note.archived"
                    class="mt-2 text-xs uppercase tracking-wide text-muted"
                >
                    В архиве
                </p>
            </div>
            <button
                type="button"
                class="shrink-0 text-sm text-muted transition hover:text-red-700"
                @click="emit('delete', note)"
            >
                Удалить
            </button>
        </div>
    </article>
</template>
