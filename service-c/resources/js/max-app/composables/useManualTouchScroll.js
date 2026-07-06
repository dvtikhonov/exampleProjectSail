/**
 * Ручная touch-прокрутка для webview MAX/Telegram, где overflow:auto часто не срабатывает.
 */
import { onMounted, onUnmounted, unref, watch } from 'vue';

/**
 * @param {import('vue').Ref<HTMLElement|null>} containerRef
 */
export function useManualTouchScroll(containerRef) {
    let startX = 0;
    let startY = 0;
    let scrollLeft = 0;
    let scrollTop = 0;
    let tracking = false;
    /** @type {HTMLElement|null} */
    let boundElement = null;

    /**
     * @param {TouchEvent} event
     */
    function onTouchStart(event) {
        const element = unref(containerRef);

        if (!element || event.touches.length !== 1) {
            return;
        }

        tracking = true;
        startX = event.touches[0].pageX;
        startY = event.touches[0].pageY;
        scrollLeft = element.scrollLeft;
        scrollTop = element.scrollTop;
    }

    /**
     * @param {TouchEvent} event
     */
    function onTouchMove(event) {
        if (!tracking) {
            return;
        }

        const element = unref(containerRef);

        if (!element || event.touches.length !== 1) {
            return;
        }

        const deltaX = startX - event.touches[0].pageX;
        const deltaY = startY - event.touches[0].pageY;

        if (Math.abs(deltaX) < 8 && Math.abs(deltaY) < 8) {
            return;
        }

        element.scrollLeft = scrollLeft + deltaX;
        element.scrollTop = scrollTop + deltaY;
    }

    function onTouchEnd() {
        tracking = false;
    }

    /**
     * @param {HTMLElement} element
     */
    function attach(element) {
        element.addEventListener('touchstart', onTouchStart, { passive: true });
        element.addEventListener('touchmove', onTouchMove, { passive: true });
        element.addEventListener('touchend', onTouchEnd, { passive: true });
        element.addEventListener('touchcancel', onTouchEnd, { passive: true });
    }

    /**
     * @param {HTMLElement} element
     */
    function detach(element) {
        element.removeEventListener('touchstart', onTouchStart);
        element.removeEventListener('touchmove', onTouchMove);
        element.removeEventListener('touchend', onTouchEnd);
        element.removeEventListener('touchcancel', onTouchEnd);
    }

    function bind() {
        const element = unref(containerRef);

        if (!element || element === boundElement) {
            return;
        }

        if (boundElement) {
            detach(boundElement);
        }

        boundElement = element;
        attach(element);
    }

    onMounted(() => {
        bind();
    });

    onUnmounted(() => {
        if (boundElement) {
            detach(boundElement);
            boundElement = null;
        }
    });

    watch(containerRef, () => {
        bind();
    });
}
