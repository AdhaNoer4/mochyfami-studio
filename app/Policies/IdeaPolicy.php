<?php

namespace App\Policies;

use App\Models\ContentIdea;
use App\Models\User;

class IdeaPolicy
{
    /**
     * Determine whether the user can view any ideas.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the idea.
     */
    public function view(User $user, ContentIdea $idea): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create ideas.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the idea.
     */
    public function update(User $user, ContentIdea $idea): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the idea.
     */
    public function delete(User $user, ContentIdea $idea): bool
    {
        return true;
    }

    /**
     * Determine whether the user can convert the idea into a project.
     */
    public function convertToProject(User $user, ContentIdea $idea): bool
    {
        return true;
    }
}
