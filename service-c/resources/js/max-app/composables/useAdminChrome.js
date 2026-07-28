/**
 * Chrome админки: видимость переключателя разделов (AdminSectionNav).
 * Состояние общее для AdminAppShell и feature roots (singleton).
 */
import { ref } from 'vue';

/** @type {import('vue').Ref<boolean>} */
const sectionNavVisible = ref(true);

/**
 * @returns {{ sectionNavVisible: import('vue').Ref<boolean> }}
 */
export function useAdminChrome() {
    return {
        sectionNavVisible,
    };
}
