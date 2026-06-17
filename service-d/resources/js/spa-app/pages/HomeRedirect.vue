<script setup>
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import LoadingSpinner from '../components/LoadingSpinner.vue';
import { useOrganization } from '../composables/useOrganization';

const router = useRouter();
const { fetchOrganization, organization } = useOrganization();

onMounted(async () => {
    await fetchOrganization();
    await router.replace(organization.value ? { name: 'reviews' } : { name: 'settings' });
});
</script>

<template>
    <div class="flex min-h-dvh items-center justify-center">
        <LoadingSpinner label="Загрузка…" />
    </div>
</template>
