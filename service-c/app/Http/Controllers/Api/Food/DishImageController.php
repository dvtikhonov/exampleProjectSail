<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Food\Menu\DishImageDeliveryInterface;
use App\Exceptions\Food\FoodDomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Food\ShowDishImageRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Отдача изображения блюда через same-origin URL.
 */
class DishImageController extends Controller
{
    public function __construct(
        private readonly DishImageDeliveryInterface $dishImageDelivery,
    ) {}

    /**
     * Возвращает бинарное содержимое изображения блюда.
     *
     * Удалённые блюда (soft delete) остаются доступны для истории заказов.
     */
    public function show(ShowDishImageRequest $request): Response
    {
        try {
            $file = $this->dishImageDelivery->resolveById($request->dishId());
        } catch (FoodDomainException $exception) {
            abort($exception->statusCode());
        }

        return new BinaryFileResponse(
            $file->absolutePath,
            200,
            $file->headers,
        );
    }
}
