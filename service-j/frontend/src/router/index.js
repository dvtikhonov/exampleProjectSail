import { createRouter, createWebHistory } from 'vue-router';
import SalesOutletsIndex from '@/views/SalesOutletsIndex.vue';

const router = createRouter({
    history: createWebHistory('/objects-sales-outlets-4/'),
    routes: [
        {
            path: '/',
            name: 'sales-outlets',
            component: SalesOutletsIndex,
        },
        {
            path: '/:pathMatch(.*)*',
            redirect: { name: 'sales-outlets' },
        },
    ],
});

export default router;
