export const METRIKA_COUNTER_ID = 114362777;

export const MESSENGER_GOALS = {
    telegram: 'telegram_click',
    max: 'max_click',
};

export function reachMessengerGoal(messenger) {
    const goal = MESSENGER_GOALS[messenger];
    if (!goal || typeof window.ym !== 'function') {
        return;
    }

    window.ym(METRIKA_COUNTER_ID, 'reachGoal', goal);
}
