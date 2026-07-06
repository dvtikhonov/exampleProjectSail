/**
 * Точка входа MAX mini-app (заказ еды в мессенджере).
 * Монтирует корневой компонент App.vue в #max-app.
 */
import '../../css/max-app.css';
import { createApp } from 'vue';
import App from './App.vue';

createApp(App).mount('#max-app');
