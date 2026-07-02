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
    private const int MAX_FILE_SIZE_KILOBYTES = 5120;

    public function authorize(): bool
    {
        return true;
    }

    public function wantsJson(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xls,xlsx', 'max:'.self::MAX_FILE_SIZE_KILOBYTES],
            'menu_category_id' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Выберите файл для импорта.',
            'file.mimes' => 'Допустимы только файлы .xls и .xlsx.',
            'file.max' => 'Размер файла не должен превышать 5 МБ.',
            'menu_category_id.required' => 'Выберите категорию меню.',
            'menu_category_id.integer' => 'Категория меню указана неверно.',
            'menu_category_id.min' => 'Категория меню указана неверно.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'file' => 'файл',
            'menu_category_id' => 'категория',
        ];
    }

    public function spreadsheetFile(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');

        return $file;
    }

    public function menuCategoryId(): int
    {
        return (int) $this->validated('menu_category_id');
    }
}
