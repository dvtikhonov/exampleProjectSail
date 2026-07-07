<script setup>
/**
 * Список категорий меню для администратора.
 */
import { computed, nextTick, onActivated, onDeactivated, onUnmounted, ref, watch } from 'vue';
import AppSelect from '../../components/AppSelect.vue';
import { useScrollViewport } from '../../composables/useScrollViewport';

const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
    refreshing: {
        type: Boolean,
        default: false,
    },
    deleteError: {
        type: String,
        default: '',
    },
    deleteLoadingId: {
        type: Number,
        default: null,
    },
    restaurantOptions: {
        type: Array,
        default: () => [],
    },
    filterRestaurantId: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['add', 'edit', 'delete', 'refresh', 'filter-restaurant']);

const listViewportRef = ref(null);

const {
    refreshViewport,
    readScrollTop,
    applyScrollTop,
} = useScrollViewport(listViewportRef, { enableTouchScroll: true });

const pullDistance = ref(0);
const isPulling = ref(false);
let touchStartY = 0;
let savedScrollTop = 0;

const PULL_THRESHOLD = 72;

const restaurantSelectOptions = computed(() => [
    { value: '', label: 'Все рестораны' },
    ...props.restaurantOptions.map((restaurant) => ({
        value: String(restaurant.id),
        label: restaurant.name,
    })),
]);

function onTouchStart(event) {
    if (readScrollTop() > 0 || props.loading || props.refreshing) {
        isPulling.value = false;

        return;
    }

    touchStartY = event.touches[0].clientY;
    isPulling.value = true;
}

function onTouchMove(event) {
    if (!isPulling.value) {
        return;
    }

    const delta = event.touches[0].clientY - touchStartY;

    if (delta <= 0) {
        pullDistance.value = 0;

        return;
    }

    pullDistance.value = Math.min(delta * 0.5, PULL_THRESHOLD * 1.5);
}

function onTouchEnd() {
    if (isPulling.value && pullDistance.value >= PULL_THRESHOLD && !props.refreshing) {
        emit('refresh');
    }

    isPulling.value = false;
    pullDistance.value = 0;
}

function captureScrollPosition() {
    savedScrollTop = readScrollTop();
}

function restoreScrollPosition() {
    const apply = () => {
        if (savedScrollTop > 0) {
            applyScrollTop(savedScrollTop);
        }
    };

    nextTick(() => {
        apply();
        requestAnimationFrame(() => {
            apply();
        });
    });
}

let pullListenersBound = false;

function bindPullToRefreshListeners() {
    const element = listViewportRef.value;

    if (pullListenersBound || !element) {
        return;
    }

    pullListenersBound = true;
    element.addEventListener('touchstart', onTouchStart, { passive: true });
    element.addEventListener('touchmove', onTouchMove, { passive: true });
    element.addEventListener('touchend', onTouchEnd, { passive: true });
}

function unbindPullToRefreshListeners() {
    const element = listViewportRef.value;

    if (!pullListenersBound || !element) {
        return;
    }

    pullListenersBound = false;
    element.removeEventListener('touchstart', onTouchStart);
    element.removeEventListener('touchmove', onTouchMove);
    element.removeEventListener('touchend', onTouchEnd);
}

onUnmounted(() => {
    unbindPullToRefreshListeners();
});

onActivated(() => {
    refreshViewport();
    bindPullToRefreshListeners();
    restoreScrollPosition();
});

onDeactivated(() => {
    unbindPullToRefreshListeners();
    isPulling.value = false;
    pullDistance.value = 0;
});

watch(listViewportRef, () => {
    bindPullToRefreshListeners();
    refreshViewport();
});

watch(
    () => [props.loading, props.refreshing, props.categories.length],
    () => {
        refreshViewport();
    },
);

function onEditClick(category) {
    captureScrollPosition();
    emit('edit', category);
}
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
        <div class="shrink-0 space-y-3 border-b border-gray-100 px-4 py-3">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">Категории</h1>
                    <p class="text-sm text-max-muted">Управление категориями меню</p>
                </div>
                <button
                    type="button"
                    class="shrink-0 rounded-xl bg-max-primary px-4 py-2 text-sm font-medium text-white transition hover:bg-max-primary/90 active:scale-[0.98]"
                    @click="emit('add')"
                >
                    Добавить
                </button>
            </div>

            <AppSelect
                :model-value="filterRestaurantId"
                :options="restaurantSelectOptions"
                size="sm"
                @update:model-value="emit('filter-restaurant', $event)"
            />
        </div>

        <div
            class="flex shrink-0 items-center justify-center overflow-hidden text-xs text-max-muted transition-[height]"
            :style="{ height: `${Math.max(pullDistance, refreshing ? 40 : 0)}px` }"
        >
            <span v-if="refreshing">Обновление…</span>
            <span v-else-if="pullDistance >= PULL_THRESHOLD">Отпустите для обновления</span>
            <span v-else-if="pullDistance > 0">Потяните вниз</span>
        </div>

        <div
            ref="listViewportRef"
            class="max-app-scroll-viewport px-4 pb-4"
            tabindex="0"
            role="region"
            aria-label="Список категорий"
        >
            <div
                v-if="deleteError"
                class="mb-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
                {{ deleteError }}
            </div>

            <div v-if="loading && categories.length === 0" class="flex items-center justify-center py-16">
                <div class="h-8 w-8 animate-spin rounded-full border-4 border-max-primary border-t-transparent" />
            </div>

            <div
                v-else-if="error"
                class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
                {{ error }}
                <button
                    type="button"
                    class="mt-2 block font-medium text-red-800 underline"
                    @click="emit('refresh')"
                >
                    Повторить
                </button>
            </div>

            <div v-else-if="categories.length === 0" class="py-16 text-center text-sm text-max-muted">
                Категории не найдены
            </div>

            <ul v-else class="space-y-3">
                <li
                    v-for="category in categories"
                    :key="category.id"
                    class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="break-words text-sm font-semibold text-gray-900">{{ category.name }}</p>
                            <p class="mt-1 text-sm text-max-muted">{{ category.restaurant_name }}</p>
                            <p class="mt-1 text-xs text-max-muted">
                                Порядок: {{ category.sort_order }} · Блюд: {{ category.dishes_count }}
                            </p>
                            <p
                                class="mt-1 text-xs font-medium"
                                :class="category.is_combo_available ? 'text-green-600' : 'text-amber-600'"
                            >
                                {{ category.is_combo_available ? 'Поддерживает режим комбо' : 'Не поддерживает режим комбо' }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-3 flex gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:border-max-primary/30 hover:text-max-primary"
                            @click="onEditClick(category)"
                        >
                            Редактировать
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-red-200 px-3 py-1.5 text-sm font-medium text-red-700 transition hover:bg-red-50 disabled:opacity-50"
                            :disabled="deleteLoadingId === category.id"
                            @click="emit('delete', category)"
                        >
                            {{ deleteLoadingId === category.id ? 'Удаление…' : 'Удалить' }}
                        </button>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>
