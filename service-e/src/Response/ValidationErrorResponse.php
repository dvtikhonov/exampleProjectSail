<?php

declare(strict_types=1);

namespace App\Response;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/** JSON-ответ 422 с ошибками валидации (формат совместим с Laravel). */
final class ValidationErrorResponse
{
    public const MESSAGE = 'The given data was invalid.';

    public static function fromViolations(ConstraintViolationListInterface $violations): JsonResponse
    {
        $errors = [];

        foreach ($violations as $violation) {
            $propertyPath = self::normalizePropertyPath((string) $violation->getPropertyPath());
            $errors[$propertyPath][] = (string) $violation->getMessage();
        }

        return new JsonResponse([
            'message' => self::MESSAGE,
            'errors' => $errors,
        ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** Приводит property path Symfony к формату errors.{field} для API. */
    private static function normalizePropertyPath(string $propertyPath): string
    {
        if ($propertyPath === '') {
            return 'payload';
        }

        if (preg_match('/^columns\[(\d+)\]$/', $propertyPath, $matches) === 1) {
            return 'columns.'.$matches[1];
        }

        return $propertyPath;
    }
}
