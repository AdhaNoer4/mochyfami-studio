<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

trait ApiResponse
{
    /**
     * Return a standardized success JSON response.
     */
    protected function successResponse(
        mixed $data = [],
        ?string $message = null,
        int $statusCode = Response::HTTP_OK
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
        ], $statusCode);
    }

    /**
     * Return a standardized error JSON response.
     */
    protected function errorResponse(
        string $message = 'An error occurred.',
        mixed $errors = null,
        int $statusCode = Response::HTTP_BAD_REQUEST
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'data' => null,
            'message' => $message,
            'errors' => $errors ?? (object)[],
        ], $statusCode);
    }
}
