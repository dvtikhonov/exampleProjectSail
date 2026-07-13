import type { $Fetch } from 'ofetch';

declare module '#app' {
    interface NuxtApp {
        /** HTTP-клиент Sanctum с глобальным перехватом 401. */
        $sanctumFetch: $Fetch;
    }
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $sanctumFetch: $Fetch;
    }
}

export {};
