<script setup>
/**
 * Горизонтальные табы категорий и переключатель поиска.
 */
import { ref } from 'vue';

defineProps({
    categoryTabs: {
        type: Array,
        default: () => [],
    },
    activeCategoryId: {
        type: Number,
        default: null,
    },
    searchQuery: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:activeCategoryId', 'update:searchQuery']);

const searchOpen = ref(false);

function toggleSearch() {
    searchOpen.value = !searchOpen.value;

    if (!searchOpen.value) {
        emit('update:searchQuery', '');
    }
}

function selectCategory(categoryId) {
    emit('update:activeCategoryId', categoryId);
}
</script>

<template>
    <div class="space-y-3">
        <div v-if="searchOpen" class="px-4">
            <input
                :value="searchQuery"
                type="search"
                placeholder="Поиск блюд"
                class="w-full rounded-2xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-max-text outline-none ring-max-primary/30 placeholder:text-max-muted focus:ring-2"
                @input="emit('update:searchQuery', $event.target.value)"
            >
        </div>

        <div class="flex items-center gap-2 px-4">
            <button
                type="button"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-white text-max-text transition hover:bg-gray-50"
                :class="searchOpen && 'border-max-primary bg-max-primary/5 text-max-primary'"
                aria-label="Поиск"
                @click="toggleSearch"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="7" />
                    <path stroke-linecap="round" d="m20 20-3-3" />
                </svg>
            </button>

            <div class="flex min-w-0 flex-1 gap-2 overflow-x-auto pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                <button
                    v-for="tab in categoryTabs"
                    :key="tab.id ?? 'all'"
                    type="button"
                    class="shrink-0 rounded-full px-4 py-2 text-sm font-medium transition"
                    :class="activeCategoryId === tab.id
                        ? 'bg-menu-chip-active text-white'
                        : 'bg-menu-card text-max-text hover:bg-gray-200/80'"
                    @click="selectCategory(tab.id)"
                >
                    {{ tab.name }}
                </button>
            </div>
        </div>
    </div>
</template>
