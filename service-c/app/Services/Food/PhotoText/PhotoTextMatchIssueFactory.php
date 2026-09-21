<?php

declare(strict_types=1);

namespace App\Services\Food\PhotoText;

use App\DTO\Food\PhotoText\PhotoTextDishNameMatchResultDto;
use App\DTO\Food\PhotoText\PhotoTextIssueDto;
use App\DTO\Food\PhotoText\PhotoTextScheduleIssueDto;
use App\Enums\Food\PhotoText\PhotoTextMatchIssueCode;

/**
 * Сборка Issue DTO для пустого имени и fail матчера (order / schedule).
 *
 * Внутренний collaborator PhotoText; не инжектить из Delivery.
 */
class PhotoTextMatchIssueFactory
{
    private const EMPTY_NAME_MESSAGE = 'Пустое название блюда после нормализации.';

    public function emptyNameOrderIssue(string $rawTitle, int $quantity): PhotoTextIssueDto
    {
        return new PhotoTextIssueDto(
            code: PhotoTextMatchIssueCode::DishNotFound,
            message: self::EMPTY_NAME_MESSAGE,
            rawTitle: $rawTitle,
            quantity: $quantity,
        );
    }

    /**
     * @param  list<string>  $dates
     */
    public function emptyNameScheduleIssue(string $rawTitle, array $dates): PhotoTextScheduleIssueDto
    {
        return new PhotoTextScheduleIssueDto(
            code: PhotoTextMatchIssueCode::DishNotFound,
            message: self::EMPTY_NAME_MESSAGE,
            rawTitle: $rawTitle,
            dates: $dates,
        );
    }

    public function orderIssueFromMatchFailure(
        PhotoTextDishNameMatchResultDto $matchResult,
        string $rawTitle,
        string $searchName,
        int $quantity,
    ): PhotoTextIssueDto {
        [$code, $message] = $this->codeAndMessageFromMatchFailure($matchResult, $searchName);

        return new PhotoTextIssueDto(
            code: $code,
            message: $message,
            rawTitle: $rawTitle,
            quantity: $quantity,
        );
    }

    /**
     * @param  list<string>  $dates
     */
    public function scheduleIssueFromMatchFailure(
        PhotoTextDishNameMatchResultDto $matchResult,
        string $rawTitle,
        string $searchName,
        array $dates,
    ): PhotoTextScheduleIssueDto {
        [$code, $message] = $this->codeAndMessageFromMatchFailure($matchResult, $searchName);

        return new PhotoTextScheduleIssueDto(
            code: $code,
            message: $message,
            rawTitle: $rawTitle,
            dates: $dates,
        );
    }

    /**
     * @return array{0: PhotoTextMatchIssueCode, 1: string}
     */
    private function codeAndMessageFromMatchFailure(
        PhotoTextDishNameMatchResultDto $matchResult,
        string $searchName,
    ): array {
        return [
            $matchResult->code ?? PhotoTextMatchIssueCode::DishNotFound,
            $matchResult->message ?? 'Блюдо не найдено: '.$searchName,
        ];
    }
}
