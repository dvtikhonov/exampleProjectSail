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
