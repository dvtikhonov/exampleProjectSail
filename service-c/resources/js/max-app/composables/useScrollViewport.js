/**
 * Прокручиваемая область с фиксированной высотой для MAX webview.
 * Поддерживает touch, клавиатуру и синхронизацию высоты от viewport.
 */
import { nextTick, onMounted, onUnmounted, unref, watch } from 'vue';

/**
 * @param {import('vue').Ref<HTMLElement|null>} viewportRef
 * @param {{ keyboardStep?: number, bottomPadding?: number, autoFocus?: boolean, enableTouchScroll?: boolean }} [options]
 */
export function useScrollViewport(viewportRef, options = {}) {
    const keyboardStep = options.keyboardStep ?? 40;
    const bottomPadding = options.bottomPadding ?? 12;
    const autoFocus = options.autoFocus ?? false;
    const enableTouchScroll = options.enableTouchScroll ?? true;

    /** @type {(() => void)[]} */
    const cleanups = [];

    let touchTracking = false;
    let touchStartX = 0;
    let touchStartY = 0;
    let touchScrollLeft = 0;
    let touchScrollTop = 0;
    let viewportTouchBound = false;

    /**
     * @param {HTMLElement} element
     */
    function syncHeight(element) {
        const top = element.getBoundingClientRect().top;
        const viewportHeight = window.visualViewport?.height ?? window.innerHeight;
        const height = Math.max(160, Math.floor(viewportHeight - top - bottomPadding));

        element.style.height = `${height}px`;
        element.style.maxHeight = `${height}px`;
    }

    /**
     * @param {KeyboardEvent} event
     */
    function onKeyDown(event) {
        const element = unref(viewportRef);

        if (!element || !element.isConnected) {
            return;
        }

        if (!element.contains(document.activeElement) && document.activeElement !== element) {
            return;
        }

        let handled = false;

        switch (event.key) {
            case 'ArrowDown':
                element.scrollTop += keyboardStep;
                handled = true;
                break;
            case 'ArrowUp':
                element.scrollTop -= keyboardStep;
                handled = true;
                break;
            case 'ArrowRight':
                element.scrollLeft += keyboardStep;
                handled = true;
                break;
            case 'ArrowLeft':
                element.scrollLeft -= keyboardStep;
                handled = true;
                break;
            default:
                break;
        }

        if (handled) {
            event.preventDefault();
        }
    }

    /**
     * @param {TouchEvent} event
     */
    function onTouchStart(event) {
        const element = unref(viewportRef);

        if (!element || event.touches.length !== 1) {
            return;
        }

        touchTracking = true;
        touchStartX = event.touches[0].pageX;
        touchStartY = event.touches[0].pageY;
        touchScrollLeft = element.scrollLeft;
        touchScrollTop = element.scrollTop;
    }

    /**
     * @param {TouchEvent} event
     */
    function onTouchMove(event) {
        if (!touchTracking) {
            return;
        }

        const element = unref(viewportRef);

        if (!element || event.touches.length !== 1) {
            return;
        }

        const deltaX = touchStartX - event.touches[0].pageX;
        const deltaY = touchStartY - event.touches[0].pageY;

        if (Math.abs(deltaX) < 6 && Math.abs(deltaY) < 6) {
            return;
        }

        element.scrollLeft = touchScrollLeft + deltaX;
        element.scrollTop = touchScrollTop + deltaY;
    }

    function onTouchEnd() {
        touchTracking = false;
    }

    /**
     * @param {HTMLElement} element
     */
    function bindTouchHandlers(element) {
        if (!enableTouchScroll || viewportTouchBound) {
            return;
        }

        viewportTouchBound = true;
        element.addEventListener('touchstart', onTouchStart, { passive: true });
        element.addEventListener('touchmove', onTouchMove, { passive: true });
        element.addEventListener('touchend', onTouchEnd, { passive: true });
        element.addEventListener('touchcancel', onTouchEnd, { passive: true });

        cleanups.push(() => {
            viewportTouchBound = false;
            element.removeEventListener('touchstart', onTouchStart);
            element.removeEventListener('touchmove', onTouchMove);
            element.removeEventListener('touchend', onTouchEnd);
            element.removeEventListener('touchcancel', onTouchEnd);
        });
    }

    function refreshViewport() {
        const element = unref(viewportRef);

        if (!element) {
            return;
        }

        bindTouchHandlers(element);
        syncHeight(element);

        if (autoFocus) {
            element.focus({ preventScroll: true });
        }
    }

    function scheduleRefresh() {
        nextTick(() => {
            refreshViewport();
            requestAnimationFrame(refreshViewport);
        });
    }

    onMounted(() => {
        const onResize = () => refreshViewport();

        window.addEventListener('resize', onResize);
        window.addEventListener('keydown', onKeyDown, true);
        window.visualViewport?.addEventListener('resize', onResize);

        cleanups.push(() => {
            window.removeEventListener('resize', onResize);
            window.removeEventListener('keydown', onKeyDown, true);
            window.visualViewport?.removeEventListener('resize', onResize);
        });

        scheduleRefresh();
    });

    onUnmounted(() => {
        cleanups.forEach((cleanup) => cleanup());
        cleanups.length = 0;
    });

    watch(viewportRef, () => {
        scheduleRefresh();
    });

    return {
        refreshViewport: scheduleRefresh,
        readScrollTop() {
            const element = unref(viewportRef);

            return element?.scrollTop ?? 0;
        },
        /**
         * @param {number} value
         */
        applyScrollTop(value) {
            const element = unref(viewportRef);

            if (element) {
                element.scrollTop = value;
            }
        },
    };
}
