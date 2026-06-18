<script setup>
/**
 * Карточка приветствия на Splash (MVP-заставка, сейчас не в роутере).
 */
import AuthErrorAlert from '../AuthErrorAlert.vue';
import PrimaryButton from '../ui/PrimaryButton.vue';

defineProps({
    user: {
        type: Object,
        default: null,
    },
    isLoading: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: null,
    },
});

defineEmits(['logout']);
</script>

<template>
    <div class="text-center">
        <p class="text-sm font-medium uppercase tracking-wide text-sky-700">
            service-d
        </p>
        <h1 class="mt-3 text-3xl font-semibold text-slate-900">
            Карта торговых точек
        </h1>
        <p class="mt-4 text-base text-slate-600">
            Скоро здесь будет карта. Пока это заставка MVP без внешних SDK.
        </p>

        <p
            v-if="user"
            class="mt-6 text-sm text-slate-500"
        >
            Вы вошли как
            <span class="font-medium text-slate-800">{{ user.name }}</span>
            ({{ user.email }})
        </p>

        <div class="mt-8 space-y-3">
            <AuthErrorAlert :message="error" />

            <PrimaryButton
                variant="secondary"
                :loading="isLoading"
                @click="$emit('logout')"
            >
                {{ isLoading ? 'Выход…' : 'Выйти' }}
            </PrimaryButton>
        </div>
    </div>
</template>
