/**
 * Whitelist и ограничения для фото блюд (синхронизировано с DishPhotoAllowedExtensions на backend).
 */

/** @type {readonly string[]} */
export const DISH_PHOTO_EXTENSIONS = [
    'png',
    'jpg',
    'jpeg',
    'jpe',
    'pjp',
    'pjpeg',
    'jfif',
];

/** Значение атрибута accept для input type="file" */
export const DISH_PHOTO_ACCEPT =
    '.png,.pjp,.jpe,.jpeg,.jpg,.pjpeg,.jfif,image/png,image/jpeg';

/** Максимальный размер файла в байтах (25 МБ) */
export const DISH_PHOTO_MAX_BYTES = 25600 * 1024;

export const DISH_PHOTO_MIN_WIDTH = 800;

export const DISH_PHOTO_MIN_HEIGHT = 600;

/**
 * @param {string} filename
 * @returns {boolean}
 */
export function isAllowedDishPhotoExtension(filename) {
    const parts = filename.split('.');

    if (parts.length < 2) {
        return false;
    }

    const extension = parts.pop()?.toLowerCase() ?? '';

    return DISH_PHOTO_EXTENSIONS.includes(extension);
}

/**
 * Проверяет минимальное разрешение изображения через Image + onload.
 *
 * @param {File} file
 * @returns {Promise<{ width: number, height: number }>}
 */
export function validateDishPhotoDimensions(file) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        const objectUrl = URL.createObjectURL(file);

        img.onload = () => {
            URL.revokeObjectURL(objectUrl);

            if (
                img.naturalWidth >= DISH_PHOTO_MIN_WIDTH
                && img.naturalHeight >= DISH_PHOTO_MIN_HEIGHT
            ) {
                resolve({ width: img.naturalWidth, height: img.naturalHeight });

                return;
            }

            reject(new Error('Ширина должна быть ≥ 800 px, высота ≥ 600 px'));
        };

        img.onerror = () => {
            URL.revokeObjectURL(objectUrl);
            reject(new Error('Не удалось прочитать изображение'));
        };

        img.src = objectUrl;
    });
}
