<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Admin;

use App\DTO\Food\AdminDishDto;
use App\DTO\Food\CreateDishDto;
use App\DTO\Food\UpdateDishDto;
use App\Enums\Food\DishVatRate;
use App\Enums\Food\DishWeightUnit;
use App\Rules\MinImageDimensions;
use App\Rules\ValidDishPhotoMime;
use App\Support\DishPhotoAllowedExtensions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Общая валидация полей формы блюда (create/update).
 */
abstract class BaseDishFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function wantsJson(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, Enum|string|MinImageDimensions|ValidDishPhotoMime>>
     */
    protected function dishAttributeRules(bool $sometimes = false): array
    {
        $required = $sometimes ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255'],
            'menu_category_id' => [$required, 'integer', 'min:1'],
            'description' => [$sometimes ? 'sometimes' : 'nullable', 'nullable', 'string', 'max:5000'],
            'weight' => [$required, 'integer', 'min:1', 'max:999999'],
            'weight_unit' => [$required, Rule::enum(DishWeightUnit::class)],
            'price' => [$required, 'numeric', 'min:0', 'decimal:0,2'],
            'vat_rate' => ['nullable', 'integer', Rule::in([5, 7, 10, 20, 22])],
            'is_available' => [$required, 'boolean'],
        ];
    }

    /**
     * @return array<int, string|ValidDishPhotoMime|MinImageDimensions>
     */
    protected function photoRules(bool $required): array
    {
        $presence = $required ? 'required' : 'nullable';

        return [
            $presence,
            'file',
            DishPhotoAllowedExtensions::mimesRule(),
            'max:'.DishPhotoAllowedExtensions::MAX_SIZE_KILOBYTES,
            new ValidDishPhotoMime,
            new MinImageDimensions,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.required' => 'Загрузите фотографию блюда.',
            'photo.max' => 'Размер фотографии не должен превышать 25 МБ.',
            'menu_category_id.required' => 'Выберите категорию меню.',
            'weight.integer' => 'Вес должен быть целым числом.',
            'weight.min' => 'Вес должен быть больше нуля.',
            'price.decimal' => 'Цена должна содержать не более двух знаков после запятой.',
            'vat_rate.in' => 'Выберите допустимую ставку НДС.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'название',
            'menu_category_id' => 'категория',
            'description' => 'описание',
            'weight' => 'вес',
            'weight_unit' => 'единицы веса',
            'price' => 'цена',
            'vat_rate' => 'НДС',
            'is_available' => 'доступность',
            'photo' => 'фотография',
        ];
    }

    public function toCreateDto(): CreateDishDto
    {
        return new CreateDishDto(
            name: trim((string) $this->validated('name')),
            menuCategoryId: (int) $this->validated('menu_category_id'),
            description: $this->nullableTrimmedString('description'),
            weight: $this->formatWeight($this->validated('weight')),
            weightUnit: DishWeightUnit::from((string) $this->validated('weight_unit')),
            price: $this->formatPrice($this->validated('price')),
            vatRate: $this->parseVatRate($this->validated('vat_rate')),
            isAvailable: (bool) $this->validated('is_available'),
        );
    }

    public function toUpdateDto(): UpdateDishDto
    {
        return new UpdateDishDto(
            name: trim((string) $this->validated('name')),
            menuCategoryId: (int) $this->validated('menu_category_id'),
            description: $this->nullableTrimmedString('description'),
            weight: $this->formatWeight($this->validated('weight')),
            weightUnit: DishWeightUnit::from((string) $this->validated('weight_unit')),
            price: $this->formatPrice($this->validated('price')),
            vatRate: $this->parseVatRate($this->validated('vat_rate')),
            isAvailable: (bool) $this->validated('is_available'),
        );
    }

    public function toUpdateDtoFromExisting(AdminDishDto $existing): UpdateDishDto
    {
        $validated = $this->validated();

        return new UpdateDishDto(
            name: array_key_exists('name', $validated)
                ? trim((string) $validated['name'])
                : $existing->name,
            menuCategoryId: array_key_exists('menu_category_id', $validated)
                ? (int) $validated['menu_category_id']
                : $existing->menuCategoryId,
            description: array_key_exists('description', $validated)
                ? $this->nullableTrimmedString('description')
                : $existing->description,
            weight: array_key_exists('weight', $validated)
                ? $this->formatWeight($validated['weight'])
                : $existing->weight,
            weightUnit: array_key_exists('weight_unit', $validated)
                ? DishWeightUnit::from((string) $validated['weight_unit'])
                : DishWeightUnit::from($existing->weightUnit),
            price: array_key_exists('price', $validated)
                ? $this->formatPrice($validated['price'])
                : $existing->price,
            vatRate: array_key_exists('vat_rate', $validated)
                ? $this->parseVatRate($validated['vat_rate'])
                : DishVatRate::fromValue($existing->vatRate),
            isAvailable: array_key_exists('is_available', $validated)
                ? (bool) $validated['is_available']
                : $existing->isAvailable,
        );
    }

    public function photo(): UploadedFile
    {
        /** @var UploadedFile $photo */
        $photo = $this->file('photo');

        return $photo;
    }

    public function photoOrNull(): ?UploadedFile
    {
        $photo = $this->file('photo');

        return $photo instanceof UploadedFile ? $photo : null;
    }

    private function nullableTrimmedString(string $key): ?string
    {
        if (! $this->has($key)) {
            return null;
        }

        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }

    private function formatWeight(mixed $value): string
    {
        return (string) (int) $value;
    }

    private function formatPrice(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function parseVatRate(mixed $value): DishVatRate
    {
        if ($value === null || $value === '') {
            return DishVatRate::Exempt;
        }

        return DishVatRate::fromValue((int) $value);
    }
}
