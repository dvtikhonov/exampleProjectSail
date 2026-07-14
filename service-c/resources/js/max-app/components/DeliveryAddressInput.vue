<script setup>
/**
 * Поле ввода адреса доставки (textarea + статус сохранения).
 * Используется в шапке меню и в блоке адреса корзины.
 */
import { ref } from 'vue';

defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    savingAddress: {
        type: Boolean,
        default: false,
    },
    hasAddress: {
        type: Boolean,
        default: false,
    },
    inputId: {
        type: String,
        default: 'delivery-address',
    },
    rows: {
        type: Number,
        default: 3,
    },
    showHints: {
        type: Boolean,
        default: true,
    },
});

defineEmits(['update:modelValue', 'focus', 'input', 'blur']);

const textareaRef = ref(null);

function focus() {
    textareaRef.value?.focus();
}

defineExpose({ focus });
</script>

<template>
    <div>
        <textarea
            :id="inputId"
            ref="textareaRef"
            :value="modelValue"
            :rows="rows"
            maxlength="1000"
            placeholder="Укажите адрес доставки"
            class="w-full resize-none rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-max-primary focus:bg-white focus:outline-none focus:ring-1 focus:ring-max-primary"
            @focus="$emit('focus')"
            @input="$emit('update:modelValue', $event.target.value); $emit('input', $event.target.value)"
            @blur="$emit('blur', $event.target.value)"
        />
        <p v-if="showHints && savingAddress" class="mt-1.5 text-xs text-max-muted">Сохранение адреса…</p>
        <p v-else-if="showHints && !hasAddress" class="mt-1.5 text-xs text-amber-600">
            Укажите адрес, чтобы оформить заявку
        </p>
    </div>
</template>
