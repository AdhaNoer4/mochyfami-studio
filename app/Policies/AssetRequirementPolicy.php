<?php

namespace App\Policies;

use App\Models\AssetRequirement;
use App\Models\User;

class AssetRequirementPolicy
{
    /**
     * Determine whether the user can view asset requirements.
     */
    public function view(User $user, AssetRequirement $assetRequirement): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create asset requirements.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update an asset requirement.
     */
    public function update(User $user, AssetRequirement $assetRequirement): bool
    {
        return true;
    }

    /**
     * Determine whether the user can move an asset requirement through the
     * status pipeline.
     */
    public function transitionStatus(User $user, AssetRequirement $assetRequirement): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete an asset requirement.
     */
    public function delete(User $user, AssetRequirement $assetRequirement): bool
    {
        return true;
    }
}
