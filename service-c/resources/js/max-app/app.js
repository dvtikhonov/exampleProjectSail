/**
 * Точка входа mini-app (MAX / Telegram).
 * Монтирует App.vue в #tg-app или #max-app.
 */
import '../../css/max-app.css';
import { createApp } from 'vue';
import App from './App.vue';

const mountEl = document.getElementById('tg-app')
    ?? document.getElementById('max-app');

if (mountEl) {
    createApp(App).mount(mountEl);
}
