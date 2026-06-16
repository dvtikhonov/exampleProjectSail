import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: {
                app: 'resources/js/app.js',
                'spa-app': 'resources/js/spa-app/app.js',
            },
            buildDirectory: 'spa-build',
            refresh: true,
        }),
        vue(),
        tailwindcss(),
    ],
    server: {
        host: true,
        port: 5175,
        strictPort: true,
        hmr: {
            host: 'localhost',
            port: 5175,
            clientPort: 5175,
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
            usePolling: true,
        },
    },
});
