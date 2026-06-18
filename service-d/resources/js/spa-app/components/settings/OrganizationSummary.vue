<script setup>
/**
 * Карточка привязанной организации на странице настроек.
 *
 * edit — смена ссылки; resync — POST /organization/resync и переход к отзывам.
 */
import PrimaryButton from '../ui/PrimaryButton.vue';
import { formatCount, formatRating } from '../../utils/formatters';
import { syncStatusLabel } from '../../utils/syncStatus';

defineProps({
    organization: {
        type: Object,
        required: true,
    },
    isLoading: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['edit', 'resync']);
</script>

<template>
    <div class="mt-8 rounded-xl border border-slate-200 bg-slate-50 p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">
                    {{ organization.name }}
                </h2>
                <p class="mt-1 text-sm text-slate-600">
                    {{ organization.address }}
                </p>
                <p class="mt-2 text-xs text-slate-500">
                    ID: {{ organization.yandex_org_id }}
                </p>
            </div>
            <div class="text-right text-sm text-slate-600">
                <p>
                    Рейтинг:
                    <span class="font-medium text-slate-900">{{ formatRating(organization.average_rating) }}</span>
                </p>
                <p class="mt-1">
                    {{ formatCount(organization.ratings_count) }} оценок
                </p>
            </div>
        </div>

        <p class="mt-4 text-sm text-slate-500">
            Статус:
            <span
                class="font-medium"
                :class="organization.sync_status === 'failed' ? 'text-red-700' : 'text-slate-700'"
            >
                {{ syncStatusLabel(organization.sync_status) }}
            </span>
        </p>
        <p
            v-if="organization.sync_error"
            class="mt-2 text-sm text-red-700"
        >
            {{ organization.sync_error }}
        </p>

        <div class="mt-5 flex flex-wrap gap-3">
            <PrimaryButton
                type="button"
                variant="secondary"
                @click="$emit('edit')"
            >
                Изменить ссылку
            </PrimaryButton>
            <PrimaryButton
                type="button"
                :loading="isLoading"
                :disabled="isLoading"
                @click="$emit('resync')"
            >
                {{ isLoading ? 'Запуск…' : 'Обновить отзывы' }}
            </PrimaryButton>
        </div>
    </div>
</template>
