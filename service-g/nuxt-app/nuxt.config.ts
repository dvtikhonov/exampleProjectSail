// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
    compatibilityDate: '2025-07-15',
    devtools: { enabled: true },
    modules: ['@nuxtjs/tailwindcss'],
    css: ['~/assets/css/main.css'],
    runtimeConfig: {
        public: {
            /** Пустая строка — same-origin /api через nginx (listtodo.localhost:8080). */
            apiBase: process.env.NUXT_PUBLIC_API_BASE ?? '',
        },
    },
    devServer: {
        host: '0.0.0.0',
        port: 3000,
    },
});
