<?php

namespace App\Policies;

use App\Models\ContentProject;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Determine whether the user can view any projects.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the project.
     */
    public function view(User $user, ContentProject $project): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create projects.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the project.
     */
    public function update(User $user, ContentProject $project): bool
    {
        return true;
    }

    /**
     * Determine whether the user can transition the project status.
     */
    public function updateStatus(User $user, ContentProject $project): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the project.
     */
    public function delete(User $user, ContentProject $project): bool
    {
        return true;
    }
}
