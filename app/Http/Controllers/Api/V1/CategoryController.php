<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\ContentCategory;
use App\Services\CategoryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CategoryController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CategoryService $categoryService
    ) {}

    /**
     * Display a listing of content categories with search and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $perPage = (int) $request->query('per_page', 10);

        $categories = $this->categoryService->paginateCategories($search, $perPage);

        return $this->successResponse([
            'items' => CategoryResource::collection($categories->items()),
            'pagination' => [
                'total' => $categories->total(),
                'per_page' => $categories->perPage(),
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
            ],
        ]);
    }

    /**
     * Store a newly created category in storage.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->createCategory($request->validated());

        return $this->successResponse(
            new CategoryResource($category),
            'Category created successfully.',
            201
        );
    }

    /**
     * Display the specified category.
     */
    public function show(ContentCategory $category): JsonResponse
    {
        $category->loadCount(['ideas', 'projects']);

        return $this->successResponse(new CategoryResource($category));
    }

    /**
     * Update the specified category in storage.
     */
    public function update(UpdateCategoryRequest $request, ContentCategory $category): JsonResponse
    {
        $updatedCategory = $this->categoryService->updateCategory($category, $request->validated());

        return $this->successResponse(
            new CategoryResource($updatedCategory),
            'Category updated successfully.'
        );
    }

    /**
     * Remove the specified category from storage with delete protection.
     */
    public function destroy(ContentCategory $category): JsonResponse
    {
        try {
            $this->categoryService->deleteCategory($category);

            return $this->successResponse(null, 'Category deleted successfully.');
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        }
    }
}
