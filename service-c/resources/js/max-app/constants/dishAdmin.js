/**
 * Константы админ-потока CRUD блюд.
 */

/** Задержка debounce поиска по названию блюда (мс) */
export const NAME_SEARCH_DEBOUNCE_MS = 300;

/** Режимы отображения по доступности: Все / Доступно / Скрыто */
export const DISH_AVAILABILITY_FILTER = Object.freeze({
    all: 'all',
    available: 'available',
    hidden: 'hidden',
});

/** Опции AppSelect для режима отображения */
export const DISH_AVAILABILITY_FILTER_OPTIONS = Object.freeze([
    { value: DISH_AVAILABILITY_FILTER.all, label: 'Все' },
    { value: DISH_AVAILABILITY_FILTER.available, label: 'Доступно' },
    { value: DISH_AVAILABILITY_FILTER.hidden, label: 'Скрыто' },
]);
