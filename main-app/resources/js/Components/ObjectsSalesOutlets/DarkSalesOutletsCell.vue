<script setup>
import HeadOrganizationPoptip from '@/Components/ObjectsSalesOutlets/HeadOrganizationPoptip.vue';
import { computed } from 'vue';

const props = defineProps({
    column: {
        type: Object,
        required: true,
    },
    row: {
        type: Object,
        required: true,
    },
    saveAction: {
        type: Function,
        required: true,
    },
    poptipId: {
        type: String,
        default: null,
    },
    openPoptipId: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['open-poptip', 'close-poptip']);
const value = computed(() => props.row[props.column.key] ?? '');
const cellType = computed(() => props.column.cellType ?? 'text');
const poptipCellComponents = {
    headOrganizationPoptip: HeadOrganizationPoptip,
};
const poptipComponent = computed(() => poptipCellComponents[cellType.value] ?? null);

const statusBadgeClass = computed(() => ({
    'bg-rose-500/15 ring-rose-400/30': props.row.status === 'blocked',
    'bg-amber-500/15 ring-amber-400/30': props.row.status === 'review',
    'bg-emerald-500/15 ring-emerald-400/30': props.row.status === 'approved',
}));

const savePoptipValue = (payload) => props.saveAction(payload);
</script>

<template>
    <component
        :is="poptipComponent"
        v-if="poptipComponent"
        :row-id="row.id"
        :value="String(value)"
        :organization-type="row.head_organization_type_label || row.head_organization_type"
        variant="dark"
        :save-action="savePoptipValue"
        :poptip-id="poptipId"
        :open-poptip-id="openPoptipId"
        @open="emit('open-poptip', $event)"
        @close="emit('close-poptip')"
    />
    <span
        v-else-if="cellType === 'statusBadge'"
        class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold text-[#ff851b] ring-1"
        :class="statusBadgeClass"
    >
        {{ value }}
    </span>
    <span v-else>
        {{ value }}
    </span>
</template>
