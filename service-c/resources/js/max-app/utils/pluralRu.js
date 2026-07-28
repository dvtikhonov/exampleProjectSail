/**
 * Русская плюрализация по числу.
 *
 * @param {number} count
 * @param {string} one  Форма для 1, 21, 31… (кроме 11)
 * @param {string} few  Форма для 2–4, 22–24… (кроме 12–14)
 * @param {string} many Форма для 0, 5–20, 25–30…
 * @returns {string}
 */
export function pluralRu(count, one, few, many) {
    const n = Math.abs(Number(count)) || 0;
    const mod10 = n % 10;
    const mod100 = n % 100;

    if (mod10 === 1 && mod100 !== 11) {
        return one;
    }

    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) {
        return few;
    }

    return many;
}
