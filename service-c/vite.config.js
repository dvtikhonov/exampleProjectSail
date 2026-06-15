import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: {
                app: 'resources/js/app.js',
                'max-app': 'resources/js/max-app/app.js',
            },
            // Отдельный каталог от main-app (/build), если оба сервиса на одном домене (VPS sslip.io).
            buildDirectory: 'max-build',
            refresh: true,
        }),
        vue(),
        tailwindcss(),
    ],
    server: {
        host: true,
        port: 5174,
        strictPort: true,
        hmr: {
            host: 'localhost',
            port: 5174,
            clientPort: 5174,
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
            usePolling: true,
        },
    },
});
