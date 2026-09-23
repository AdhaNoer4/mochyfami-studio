<?php

namespace App\Services;

use App\Models\ContentCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CategoryService
{
    /**
     * Get paginated categories with optional search by name.
     */
    public function paginateCategories(?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        return ContentCategory::query()
            ->withCount(['ideas', 'projects'])
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * Create a new category.
     */
    public function createCategory(array $data): ContentCategory
    {
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        return ContentCategory::create($data);
    }

    /**
     * Update an existing category.
     */
    public function updateCategory(ContentCategory $category, array $data): ContentCategory
    {
        if (empty($data['slug']) && isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $category->update($data);

        return $category->fresh();
    }

    /**
     * Delete a category with protection against existing ideas or projects.
     *
     * @throws InvalidArgumentException
     */
    public function deleteCategory(ContentCategory $category): void
    {
        $ideasCount = $category->ideas()->count();
        $projectsCount = $category->projects()->count();

        if ($ideasCount > 0 || $projectsCount > 0) {
            throw new InvalidArgumentException(
                "Cannot delete category '{$category->name}'. It has {$ideasCount} ideas and {$projectsCount} projects associated with it."
            );
        }

        $category->delete();
    }
}
