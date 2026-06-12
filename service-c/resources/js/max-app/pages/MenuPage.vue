<script setup>
import { computed } from 'vue';
import DishImage from '../components/DishImage.vue';

const props = defineProps({
    menu: {
        type: Object,
        default: null,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
    addingDishId: {
        type: Number,
        default: null,
    },
    cartItemCount: {
        type: Number,
        default: 0,
    },
    cartTotal: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['add-to-cart', 'open-cart']);

const hasCart = computed(() => props.cartItemCount > 0);
</script>

<template>
    <div class="flex min-h-dvh flex-col pb-24">
        <header class="sticky top-0 z-10 border-b border-gray-200 bg-white px-4 py-3">
            <h1 class="truncate text-lg font-semibold text-gray-900">
                {{ menu?.restaurant_name ?? 'Меню' }}
            </h1>
            <p class="text-sm text-max-muted">Выберите блюда</p>
        </header>

        <main class="flex-1 px-4 py-4">
            <div v-if="loading" class="flex items-center justify-center py-16">
                <div class="h-8 w-8 animate-spin rounded-full border-4 border-max-primary border-t-transparent" />
            </div>

            <div
                v-else-if="error"
                class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
                {{ error }}
            </div>

            <div v-else-if="menu" class="space-y-6">
                <section v-for="category in menu.categories" :key="category.id">
                    <h2 class="mb-3 text-base font-semibold text-gray-800">{{ category.name }}</h2>
                    <ul class="space-y-2">
                        <li
                            v-for="dish in category.dishes"
                            :key="dish.id"
                            class="flex items-center gap-3 rounded-2xl border border-gray-100 bg-white p-3 shadow-sm"
                        >
                            <DishImage :image-url="dish.image_url" :alt="dish.name" />
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-gray-900">{{ dish.name }}</p>
                                <p class="mt-0.5 text-sm font-semibold text-max-primary">{{ dish.price }} ₽</p>
                                <p v-if="!dish.is_available" class="mt-1 text-xs text-red-500">Недоступно</p>
                            </div>
                            <button
                                type="button"
                                class="flex h-9 min-w-9 items-center justify-center rounded-full bg-max-primary px-3 text-sm font-medium text-white transition hover:bg-max-primary-hover disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="!dish.is_available || addingDishId === dish.id"
                                @click="emit('add-to-cart', dish)"
                            >
                                <span v-if="addingDishId === dish.id" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                                <span v-else>+</span>
                            </button>
                        </li>
                    </ul>
                </section>
            </div>
        </main>

        <div
            v-if="hasCart"
            class="fixed inset-x-0 bottom-0 z-20 border-t border-gray-200 bg-white px-4 py-3 safe-area-bottom"
        >
            <button
                type="button"
                class="flex w-full items-center justify-between rounded-2xl bg-max-primary px-4 py-3.5 text-white transition hover:bg-max-primary-hover"
                @click="emit('open-cart')"
            >
                <span class="font-medium">Корзина · {{ cartItemCount }} {{ cartItemCount === 1 ? 'позиция' : 'позиций' }}</span>
                <span class="font-semibold">{{ cartTotal }} ₽</span>
            </button>
        </div>
    </div>
</template>
