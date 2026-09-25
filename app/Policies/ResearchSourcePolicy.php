<?php

namespace App\Policies;

use App\Models\Source;
use App\Models\User;

class ResearchSourcePolicy
{
    /**
     * Determine whether the user can view any sources.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the source.
     */
    public function view(User $user, Source $source): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create sources.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the source.
     */
    public function update(User $user, Source $source): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the source.
     */
    public function delete(User $user, Source $source): bool
    {
        return true;
    }
}
