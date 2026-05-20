<?php

namespace App\Services\ObjectsSalesOutlets;

use App\DTO\ObjectsSalesOutlets\SalesOutletIndexQueryDto;
use App\DTO\ObjectsSalesOutlets\SalesOutletsPageDto;
use App\Services\MicroserviceHttpClient;
use Illuminate\Http\Client\RequestException;

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
}
