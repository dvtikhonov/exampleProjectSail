<script setup>
/**
 * Форма поиска организации по URL и текстовому уточнению.
 *
 * Строка «ссылка + город/адрес» уходит в POST /organization/resolve.
 * cancel виден только в режиме редактирования уже привязанной организации.
 */
import AuthErrorAlert from '../AuthErrorAlert.vue';
import PrimaryButton from '../ui/PrimaryButton.vue';

const url = defineModel('url', {
    type: String,
    default: '',
});

defineProps({
    isResolving: {
        type: Boolean,
        default: false,
    },
    isEditing: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: null,
    },
    isBusy: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['submit', 'cancel']);
</script>

<template>
    <div class="mt-8">
        <form
            class="space-y-4"
            @submit.prevent="$emit('submit')"
        >
            <div>
                <label
                    class="mb-1 block text-sm font-medium text-slate-700"
                    for="organization-search"
                >
                    Ссылка и уточнение
                </label>
                <input
                    id="organization-search"
                    v-model="url"
                    type="text"
                    required
                    placeholder="www.invitro.ru Новокузнецк"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-sky-500 focus:ring-2"
                >
                <p class="mt-2 text-xs text-slate-500">
                    Сначала укажите ссылку на сайт или Яндекс.Карты, затем через пробел — уточнение
                    (город, адрес, филиал). Пример:
                    <code class="rounded bg-slate-100 px-1">www.invitro.ru Новокузнецк</code>
                    или
                    <code class="rounded bg-slate-100 px-1">https://yandex.ru/maps/...</code>.
                </p>
            </div>

            <AuthErrorAlert :message="error" />

            <div class="flex flex-wrap gap-3">
                <PrimaryButton
                    type="submit"
                    :loading="isResolving"
                    :disabled="isBusy"
                >
                    {{ isResolving ? 'Поиск…' : 'Найти организацию' }}
                </PrimaryButton>
                <PrimaryButton
                    v-if="isEditing"
                    type="button"
                    variant="secondary"
                    @click="$emit('cancel')"
                >
                    Отмена
                </PrimaryButton>
            </div>
        </form>
    </div>
</template>
