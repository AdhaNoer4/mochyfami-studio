<?php

namespace App\Policies;

use App\Models\ContentCategory;
use App\Models\User;

class CategoryPolicy
{
    /**
     * Determine whether the user can view any categories.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the category.
     */
    public function view(User $user, ContentCategory $category): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create categories.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the category.
     */
    public function update(User $user, ContentCategory $category): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the category.
     */
    public function delete(User $user, ContentCategory $category): bool
    {
        return true;
    }
}
