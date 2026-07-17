import { computed, ref } from 'vue';

/**
 * Соотношение зон карточки заказа: активная 3/5, неактивная 2/5 высоты.
 *
 * @param {'details'|'chat'} [initial='details']
 */
export function useOrderDetailPaneLayout(initial = 'details') {
    const activePane = ref(initial);

    function activateDetails() {
        activePane.value = 'details';
    }

    function activateChat() {
        activePane.value = 'chat';
    }

    const isDetailsActive = computed(() => activePane.value === 'details');
    const isChatActive = computed(() => activePane.value === 'chat');

    const detailsPaneClass = computed(() => [
        'min-h-0',
        isDetailsActive.value ? 'flex-[3]' : 'flex-[2]',
    ]);

    const chatPaneClass = computed(() => [
        'min-h-0',
        isChatActive.value ? 'flex-[3]' : 'flex-[2]',
    ]);

    const detailsActiveSurfaceClass = computed(() =>
        isDetailsActive.value
            ? 'border-max-primary/50'
            : 'border-gray-100',
    );

    return {
        activePane,
        activateDetails,
        activateChat,
        isDetailsActive,
        isChatActive,
        detailsPaneClass,
        chatPaneClass,
        detailsActiveSurfaceClass,
    };
}
