<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Валидация multipart-запроса импорта блюд из XLS/XLSX.
 */
class ImportDishesSpreadsheetRequest extends FormRequest
{
    private const int MAX_FILE_KILOBYTES = 5120;

    /**
     * Разрешает выполнение запроса.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Всегда ожидает JSON-ответ.
     */
    public function wantsJson(): bool
    {
        return true;
    }

    /**
     * Правила валидации файла таблицы и категории меню.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xls,xlsx', 'max:'.self::MAX_FILE_KILOBYTES],
            'menu_category_id' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Сообщения об ошибках валидации импорта.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Выберите файл таблицы.',
            'file.mimes' => 'Допустимы только файлы .xls и .xlsx.',
            'file.max' => 'Размер файла не должен превышать 5 МБ.',
            'menu_category_id.required' => 'Выберите категорию меню.',
        ];
    }

    /**
     * Человекочитаемые имена атрибутов.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'file' => 'файл',
            'menu_category_id' => 'категория',
        ];
    }

    /**
     * Возвращает загруженный файл таблицы.
     */
    public function spreadsheetFile(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');

        return $file;
    }

    /**
     * Возвращает ID категории меню из валидированных данных.
     */
    public function menuCategoryId(): int
    {
        return (int) $this->validated('menu_category_id');
    }
}
