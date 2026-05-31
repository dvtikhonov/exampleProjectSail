<?php

namespace App\Http\Controllers;

use App\DTO\ObjectsSalesOutlets\SalesOutletIndexQueryDto;
use App\Services\ObjectsSalesOutlets\SalesOutletsApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class ObjectsSalesOutletsController extends Controller
{
    public function __construct(
        private readonly SalesOutletsApiClient $salesOutletsApiClient,
    ) {}

    public function index(Request $request): Response
    {
        return $this->renderIndex($request, 'ObjectsSalesOutlets/Index', 'objectsSalesOutlets.index');
    }

    public function darkIndex(Request $request): Response
    {
        return $this->renderIndex($request, 'ObjectsSalesOutlets/DarkIndex', 'objectsSalesOutlets.darkIndex');
    }

    public function createExport(Request $request): JsonResponse
    {
        return response()->json(
            $this->salesOutletsApiClient->createReport($request->all(), 'csv_download'),
        );
    }

    public function exportStatus(string $uuid): JsonResponse
    {
        return response()->json(
            $this->salesOutletsApiClient->reportStatus($uuid),
        );
    }

    public function downloadExport(string $uuid): HttpResponse
    {
        $response = $this->salesOutletsApiClient->downloadReport($uuid);

        return response($response->body(), $response->status())
            ->withHeaders($this->downloadHeaders($response->headers()));
    }

    public function createMail(Request $request): JsonResponse
    {
        return response()->json(
            $this->salesOutletsApiClient->createReport($request->all(), 'html_email'),
        );
    }

    public function mailStatus(string $uuid): JsonResponse
    {
        return response()->json(
            $this->salesOutletsApiClient->reportStatus($uuid),
        );
    }

    public function reportStats(): JsonResponse
    {
        return response()->json(
            $this->salesOutletsApiClient->reportStats(),
        );
    }

    private function renderIndex(Request $request, string $component, string $routeName): Response
    {
        $page = $this->salesOutletsApiClient->index(
            SalesOutletIndexQueryDto::fromRequest($request),
        );

        return Inertia::render($component, $page->toInertiaProps($routeName));
    }

    /**
     * @param  array<string, array<int, string>>  $headers
     * @return array<string, string>
     */
    private function downloadHeaders(array $headers): array
    {
        $allowedHeaders = ['content-type', 'content-disposition', 'content-length'];
        $downloadHeaders = [];
        $normalizedHeaders = array_change_key_case($headers, CASE_LOWER);

        foreach ($allowedHeaders as $header) {
            $value = $normalizedHeaders[$header][0] ?? null;

            if ($value !== null) {
                $downloadHeaders[$header] = $value;
            }
        }

        return $downloadHeaders;
    }
}
