<?php

namespace App\Http\Controllers;

use App\DTO\ObjectsSalesOutlets\SalesOutletIndexQueryDto;
use App\Services\ObjectsSalesOutlets\SalesOutletsApiClient;
use Illuminate\Http\Request;
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

    private function renderIndex(Request $request, string $component, string $routeName): Response
    {
        $page = $this->salesOutletsApiClient->index(
            SalesOutletIndexQueryDto::fromRequest($request),
        );

        return Inertia::render($component, $page->toInertiaProps($routeName));
    }
}
