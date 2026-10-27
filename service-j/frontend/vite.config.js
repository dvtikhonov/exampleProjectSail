import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';

const gatewayOrigin = process.env.VITE_GATEWAY_ORIGIN ?? 'http://localhost:8080';

export default defineConfig({
    base: '/objects-sales-outlets-4/',
    plugins: [vue()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./src', import.meta.url)),
        },
    },
    server: {
        host: '0.0.0.0',
        port: 5174,
        proxy: {
            // Same-origin API + token endpoint via nginx-gateway (no CORS).
            '/api/j': {
                target: gatewayOrigin,
                changeOrigin: true,
            },
            '/get-api-token': {
                target: gatewayOrigin,
                changeOrigin: true,
            },
        },
    },
    build: {
        outDir: 'dist',
        emptyOutDir: true,
    },
});
