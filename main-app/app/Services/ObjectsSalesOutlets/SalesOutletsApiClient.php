<?php

namespace App\Services\ObjectsSalesOutlets;

use App\DTO\ObjectsSalesOutlets\SalesOutletIndexQueryDto;
use App\DTO\ObjectsSalesOutlets\SalesOutletsPageDto;
use App\Services\MicroserviceHttpClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;

class SalesOutletsApiClient
{
    public function __construct(
        private readonly MicroserviceHttpClient $httpClient,
    ) {}

    /**
     * @throws RequestException
     */
    public function index(SalesOutletIndexQueryDto $queryDto): SalesOutletsPageDto
    {
        $response = $this->httpClient->serviceA('get', 'sales-outlets', $queryDto->toQuery());
        $response->throw();

        return SalesOutletsPageDto::fromServicePayload($response->json() ?? []);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function createReport(array $payload, string $reportType): array
    {
        $response = $this->httpClient->serviceB('post', 'sales-outlets/reports', [
            ...$payload,
            'report_type' => $reportType,
        ]);
        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function reportStatus(string $uuid): array
    {
        $response = $this->httpClient->serviceB('get', "sales-outlets/reports/{$uuid}");
        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function reportStats(): array
    {
        $response = $this->httpClient->serviceB('get', 'sales-outlets/reports/stats');
        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @throws RequestException
     */
    public function downloadReport(string $uuid): Response
    {
        $response = $this->httpClient->serviceB('get', "sales-outlets/reports/{$uuid}/download");
        $response->throw();

        return $response;
    }
}
