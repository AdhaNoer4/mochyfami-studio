<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    use ApiResponse;

    /**
     * Handle the incoming request for dashboard metrics.
     */
    public function __invoke(DashboardService $dashboardService): JsonResponse
    {
        return $this->successResponse($dashboardService->dashboard());
    }
}
