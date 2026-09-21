/**
 * Точка входа Vue 3 SPA (Notes CRUD).
 *
 * Роутер + Pinia; ассеты собираются Vite в public/build (docker multi-stage).
 */
import '../css/app.css';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';

createApp(App).use(createPinia()).use(router).mount('#app');
