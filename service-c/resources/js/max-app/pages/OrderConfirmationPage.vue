<script setup>
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
        <div class="mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-green-100 text-4xl">
            ✓
        </div>
        <h1 class="text-2xl font-bold text-gray-900">Заявка принята</h1>
        <p class="mt-2 text-sm text-max-muted">
            Заказ №{{ order.id }} отправлен в {{ order.restaurant_name }}
        </p>

        <div class="mt-8 w-full max-w-sm rounded-2xl border border-gray-100 bg-white p-4 text-left shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <span class="text-sm text-max-muted">Сумма</span>
                <span class="text-lg font-bold text-gray-900">{{ order.total }} ₽</span>
            </div>
            <ul class="mt-3 space-y-2">
                <li
                    v-for="(item, index) in order.items_snapshot"
                    :key="index"
                    class="flex items-center justify-between text-sm"
                >
                    <span class="text-gray-700">{{ item.dish_name }} × {{ item.quantity }}</span>
                    <span class="font-medium text-gray-900">{{ item.line_total }} ₽</span>
                </li>
            </ul>
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
