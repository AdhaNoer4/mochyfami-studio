<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    use ApiResponse;

    /**
     * Check backend system health.
     */
    public function __invoke(): JsonResponse
    {
        return $this->successResponse([
            'status' => 'ok',
            'app' => config('app.name', 'MochyFami Content Studio'),
            'environment' => config('app.env'),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
