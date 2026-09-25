<?php

namespace App\Policies;

use App\Models\ResearchClaim;
use App\Models\User;

class ResearchClaimPolicy
{
    /**
     * Determine whether the user can view any research claims.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the research claim.
     */
    public function view(User $user, ResearchClaim $claim): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create research claims.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the research claim.
     */
    public function update(User $user, ResearchClaim $claim): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the research claim.
     */
    public function delete(User $user, ResearchClaim $claim): bool
    {
        return true;
    }
}
