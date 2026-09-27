<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\IdeaController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ResearchClaimController;
use App\Http\Controllers\Api\V1\ResearchController;
use App\Http\Controllers\Api\V1\ResearchDiscoveryController;
use App\Http\Controllers\Api\V1\ResearchGenerationController;
use App\Http\Controllers\Api\V1\ResearchPipelineController;
use App\Http\Controllers\Api\V1\ResearchQualityController;
use App\Http\Controllers\Api\V1\ResearchScriptContextController;
use App\Http\Controllers\Api\V1\ResearchSourceController;
use App\Http\Controllers\Api\V1\ScriptController;
use App\Http\Controllers\Api\V1\ScriptGenerationController;
use App\Http\Controllers\Api\V1\ScriptQualityController;
use App\Http\Controllers\Api\V1\ScriptVersionController;
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

        Route::prefix('projects/{project}/research')->group(function () {
            Route::post('/', [ResearchController::class, 'store']);
            Route::get('/', [ResearchController::class, 'show']);
            Route::patch('/', [ResearchController::class, 'update']);
            Route::patch('/status', [ResearchController::class, 'transitionStatus']);
            Route::delete('/', [ResearchController::class, 'destroy']);

            Route::get('pipeline', [ResearchPipelineController::class, 'show']);

            Route::get('quality', [ResearchQualityController::class, 'show']);

            Route::get('script-context', [ResearchScriptContextController::class, 'show']);

            Route::post('discover', [ResearchDiscoveryController::class, 'discover']);

            Route::post('generate', [ResearchGenerationController::class, 'generate']);

            Route::get('sources', [ResearchSourceController::class, 'index']);
            Route::post('sources', [ResearchSourceController::class, 'store']);
            Route::patch('sources/{source}', [ResearchSourceController::class, 'update']);
            Route::delete('sources/{source}', [ResearchSourceController::class, 'destroy']);

            Route::get('claims', [ResearchClaimController::class, 'index']);
            Route::post('claims', [ResearchClaimController::class, 'store']);
            Route::patch('claims/{claim}', [ResearchClaimController::class, 'update']);
            Route::delete('claims/{claim}', [ResearchClaimController::class, 'destroy']);
            Route::get('claims/{claim}/sources', [ResearchClaimController::class, 'sources']);
            Route::post('claims/{claim}/sources/{source}', [ResearchClaimController::class, 'attachSource']);
            Route::delete('claims/{claim}/sources/{source}', [ResearchClaimController::class, 'detachSource']);
        });

        Route::prefix('projects/{project}/script')->group(function () {
            Route::post('/', [ScriptController::class, 'store']);
            Route::get('/', [ScriptController::class, 'show']);
            Route::patch('/status', [ScriptController::class, 'transitionStatus']);

            Route::post('generate', [ScriptGenerationController::class, 'generate']);

            Route::get('quality', [ScriptQualityController::class, 'show']);

            Route::get('versions', [ScriptVersionController::class, 'index']);
            Route::post('versions', [ScriptVersionController::class, 'store']);
            Route::patch('versions/current', [ScriptVersionController::class, 'updateCurrent']);
            Route::get('versions/{version}', [ScriptVersionController::class, 'show'])->whereNumber('version');
        });
    });
});
