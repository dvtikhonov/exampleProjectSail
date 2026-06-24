<script setup>
import DishImage from '../components/DishImage.vue';

defineProps({
    order: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['back-to-restaurants']);
</script>

<template>
    <div class="flex min-h-dvh flex-col items-center justify-center px-6 py-12 text-center">
        <div class="mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-amber-100 text-4xl">
            ⏳
        </div>
        <h1 class="text-2xl font-bold text-gray-900">Заявка отправлена на проверку</h1>
        <p class="mt-2 text-sm text-max-muted">
            Заказ №{{ order.id }} ожидает подтверждения. Мы сообщим вам в MAX, когда заявка будет обработана.
        </p>

        <div class="mt-8 w-full max-w-sm rounded-2xl border border-gray-100 bg-white p-4 text-left shadow-sm">
            <div class="border-b border-gray-100 pb-3">
                <p class="text-xs font-medium uppercase tracking-wide text-max-muted">Адрес доставки</p>
                <p class="mt-1 text-sm text-gray-900">{{ order.delivery_address }}</p>
            </div>

            <ul class="mt-3 space-y-2">
                <li
                    v-for="(item, index) in order.items_snapshot"
                    :key="index"
                    class="flex items-center gap-3 text-sm"
                >
                    <DishImage :image-url="item.image_url" :alt="item.dish_name" size="sm" />
                    <span class="min-w-0 flex-1 text-gray-700">{{ item.dish_name }} × {{ item.quantity }}</span>
                    <span class="shrink-0 font-medium text-gray-900">{{ item.line_total }} ₽</span>
                </li>
            </ul>

            <div class="mt-4 border-t border-gray-100 pt-3 text-sm">
                <template v-if="order.delivery_applicable">
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="text-max-muted">Сумма блюд</span>
                            <span class="font-medium text-gray-900">{{ order.items_total }} ₽</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-max-muted">Доставка</span>
                            <span class="font-medium text-gray-900">{{ order.delivery_cost }} ₽</span>
                        </div>
                        <div class="flex items-center justify-between border-t border-gray-100 pt-2">
                            <span class="font-medium text-gray-900">Итого</span>
                            <span class="text-lg font-bold text-gray-900">{{ order.total }} ₽</span>
                        </div>
                    </div>
                </template>
                <div v-else class="flex items-center justify-between">
                    <span class="font-medium text-gray-900">Итого</span>
                    <span class="text-lg font-bold text-gray-900">{{ order.total }} ₽</span>
                </div>
            </div>
        </div>

        <button
            type="button"
            class="mt-8 w-full max-w-sm rounded-2xl bg-max-primary px-6 py-3.5 font-medium text-white transition hover:bg-max-primary-hover"
            @click="emit('back-to-restaurants')"
        >
            К ресторанам
        </button>
    </div>
</template>
