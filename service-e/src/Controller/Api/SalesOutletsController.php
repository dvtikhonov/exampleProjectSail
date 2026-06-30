<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Contract\SalesOutlets\SalesOutletRepositoryInterface;
use App\Contract\SalesOutlets\SalesOutletServiceInterface;
use App\Contract\SalesOutlets\SalesOutletsMetadataRepositoryInterface;
use App\Contract\SalesOutlets\SalesOutletTableMetaProviderInterface;
use App\Domain\SalesOutlet;
use App\Input\SalesOutlets\IndexSalesOutletsInput;
use App\Input\SalesOutlets\UpdateHeadOrganizationInput;
use App\Input\SalesOutlets\UpdateSalesOutletInput;
use App\Presentation\SalesOutletRowPresenter;
use App\Response\SalesOutletIndexResponse;
use App\Response\ValidationErrorResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * REST API торговых точек. Формат ответов совместим с service-a.
 */
#[Route('/api')]
final class SalesOutletsController
{
    public function __construct(
        private readonly SalesOutletServiceInterface $salesOutletService,
        private readonly SalesOutletRepositoryInterface $salesOutletRepository,
        private readonly SalesOutletsMetadataRepositoryInterface $metadataRepository,
        private readonly SalesOutletTableMetaProviderInterface $tableMetaProvider,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /** Список торговых точек с фильтрами, сортировкой и пагинацией. */
    #[Route('/sales-outlets', name: 'sales_outlets_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $input = IndexSalesOutletsInput::fromRequest($request);
        $violations = $this->validator->validate($input);

        if (count($violations) > 0) {
            return ValidationErrorResponse::fromViolations($violations);
        }

        $result = $this->salesOutletService->index(
            $input->toQueryDto($this->metadataRepository),
        );

        return new JsonResponse(
            SalesOutletIndexResponse::from($result, $this->tableMetaProvider),
        );
    }

    /** Обновление полей торговой точки. */
    #[Route('/sales-outlets/{id}', name: 'sales_outlets_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $salesOutlet = $this->resolveSalesOutlet($id);
        $input = UpdateSalesOutletInput::fromRequest($request);
        $violations = $this->validator->validate($input);

        if (count($violations) > 0) {
            return ValidationErrorResponse::fromViolations($violations);
        }

        $updated = $this->salesOutletService->update($salesOutlet, $input->toDto());

        return new JsonResponse(
            SalesOutletRowPresenter::fromDomain($updated)->toArray(),
        );
    }

    /** Обновление головной организации торговой точки. */
    #[Route('/sales-outlets/{id}/head-organization', name: 'sales_outlets_update_head_organization', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function updateHeadOrganization(int $id, Request $request): JsonResponse
    {
        $salesOutlet = $this->resolveSalesOutlet($id);
        $input = UpdateHeadOrganizationInput::fromRequest($request);
        $violations = $this->validator->validate($input);

        if (count($violations) > 0) {
            return ValidationErrorResponse::fromViolations($violations);
        }

        $updated = $this->salesOutletService->updateHeadOrganization($salesOutlet, $input->toDto());

        return new JsonResponse(
            SalesOutletRowPresenter::fromDomain($updated)->toArray(),
        );
    }

    /** Мягкое удаление торговой точки (soft delete). */
    #[Route('/sales-outlets/{id}', name: 'sales_outlets_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function destroy(int $id): Response
    {
        $salesOutlet = $this->resolveSalesOutlet($id);
        $this->salesOutletService->delete($salesOutlet);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /** Загружает торговую точку по id или выбрасывает 404. */
    private function resolveSalesOutlet(int $id): SalesOutlet
    {
        $salesOutlet = $this->salesOutletRepository->findById($id);

        if (null === $salesOutlet) {
            throw new NotFoundHttpException(sprintf('Sales outlet with id %d not found.', $id));
        }

        return $salesOutlet;
    }
}
