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
    public function createExport(array $payload): array
    {
        $response = $this->httpClient->serviceB('post', 'sales-outlets/exports', $payload);
        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function exportStatus(string $uuid): array
    {
        $response = $this->httpClient->serviceB('get', "sales-outlets/exports/{$uuid}");
        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @throws RequestException
     */
    public function downloadExport(string $uuid): Response
    {
        $response = $this->httpClient->serviceB('get', "sales-outlets/exports/{$uuid}/download");
        $response->throw();

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function createMail(array $payload): array
    {
        $response = $this->httpClient->serviceB('post', 'sales-outlets/mail', $payload);
        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function mailStatus(string $uuid): array
    {
        $response = $this->httpClient->serviceB('get', "sales-outlets/mail/{$uuid}");
        $response->throw();

        return $response->json() ?? [];
    }
}
