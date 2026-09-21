import { createRouter, createWebHistory } from 'vue-router';
import NotesIndex from '../pages/Notes/Index.vue';
import NotesEdit from '../pages/Notes/Edit.vue';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', redirect: '/notes' },
        { path: '/notes', name: 'notes.index', component: NotesIndex },
        { path: '/notes/new', name: 'notes.create', component: NotesEdit },
        { path: '/notes/:id/edit', name: 'notes.edit', component: NotesEdit, props: true },
        { path: '/:pathMatch(.*)*', redirect: '/notes' },
    ],
});

export default router;
