import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    base: '/messenger/',
    plugins: [vue()],
    server: {
        host: '0.0.0.0',
        port: 5175,
    },
    build: {
        outDir: 'dist',
        emptyOutDir: true,
    },
});
