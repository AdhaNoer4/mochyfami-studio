<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\IdeaController;
use App\Http\Controllers\Api\V1\ProjectController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class);

    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/dashboard', DashboardController::class);
        Route::apiResource('categories', CategoryController::class);
        Route::post('ideas/import/preview', [IdeaController::class, 'previewImport']);
        Route::post('ideas/import', [IdeaController::class, 'executeImport']);
        Route::post('ideas/{idea}/convert-to-project', [IdeaController::class, 'convertToProject']);
        Route::apiResource('ideas', IdeaController::class);
        Route::patch('projects/{project}/status', [ProjectController::class, 'transitionStatus']);
        Route::apiResource('projects', ProjectController::class);
    });
});
