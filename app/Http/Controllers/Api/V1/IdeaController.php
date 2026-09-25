<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\IdeaAlreadyConvertedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Idea\ConvertIdeaToProjectRequest;
use App\Http\Requests\Idea\ExecuteImportRequest;
use App\Http\Requests\Idea\GetIdeasRequest;
use App\Http\Requests\Idea\PreviewImportRequest;
use App\Http\Requests\Idea\StoreIdeaRequest;
use App\Http\Requests\Idea\UpdateIdeaRequest;
use App\Http\Resources\IdeaResource;
use App\Models\ContentIdea;
use App\Services\IdeaImportService;
use App\Services\IdeaService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class IdeaController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected IdeaService $ideaService,
        protected IdeaImportService $ideaImportService
    ) {}

    /**
     * Display a listing of ideas with validated search, filters, sorting, and pagination.
     */
    public function index(GetIdeasRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 10);

        $ideas = $this->ideaService->paginateIdeas($validated, $perPage);

        return $this->successResponse([
            'items' => IdeaResource::collection($ideas->items()),
            'pagination' => [
                'total' => $ideas->total(),
                'per_page' => $ideas->perPage(),
                'current_page' => $ideas->currentPage(),
                'last_page' => $ideas->lastPage(),
            ],
        ]);
    }

    /**
     * Preview CSV file upload before import.
     */
    public function previewImport(PreviewImportRequest $request): JsonResponse
    {
        try {
            $preview = $this->ideaImportService->previewCsv($request->file('csv_file'));

            return $this->successResponse($preview, 'CSV preview generated successfully.');
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        }
    }

    /**
     * Execute transactional bulk import of valid rows.
     */
    public function executeImport(ExecuteImportRequest $request): JsonResponse
    {
        try {
            $result = $this->ideaImportService->executeImport(
                $request->validated()['rows'],
                $request->user()->id
            );

            return $this->successResponse(
                $result,
                "{$result['imported_rows']} content ideas imported successfully."
            );
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to import ideas: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Store a newly created idea.
     */
    public function store(StoreIdeaRequest $request): JsonResponse
    {
        $idea = $this->ideaService->createIdea(
            $request->validated(),
            $request->user()->id
        );

        return $this->successResponse(
            new IdeaResource($idea->load(['category', 'creator'])),
            'Content idea created successfully.',
            201
        );
    }

    /**
     * Display the specified idea.
     */
    public function show(ContentIdea $idea): JsonResponse
    {
        $idea->load(['category', 'creator']);

        return $this->successResponse(new IdeaResource($idea));
    }

    /**
     * Convert an eligible idea into a project.
     */
    public function convertToProject(ConvertIdeaToProjectRequest $request, ContentIdea $idea): JsonResponse
    {
        Gate::authorize('convertToProject', $idea);

        try {
            $result = $this->ideaService->convertIdeaToProject(
                $idea,
                $request->validated(),
                $request->user()->id
            );

            return $this->successResponse($result, 'Idea converted to project successfully.', 201);
        } catch (IdeaAlreadyConvertedException $e) {
            return $this->errorResponse($e->getMessage(), null, 409);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            return $this->errorResponse('Unexpected error during conversion.', null, 500);
        }
    }

    /**
     * Update the specified idea.
     */
    public function update(UpdateIdeaRequest $request, ContentIdea $idea): JsonResponse
    {
        $updatedIdea = $this->ideaService->updateIdea($idea, $request->validated());

        return $this->successResponse(
            new IdeaResource($updatedIdea),
            'Content idea updated successfully.'
        );
    }

    /**
     * Remove the specified idea.
     */
    public function destroy(ContentIdea $idea): JsonResponse
    {
        $this->ideaService->deleteIdea($idea);

        return $this->successResponse(null, 'Content idea deleted successfully.');
    }
}
