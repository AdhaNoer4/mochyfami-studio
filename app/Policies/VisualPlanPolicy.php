<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VisualPlan;

class VisualPlanPolicy
{
    /**
     * Determine whether the user can view the visual plan.
     */
    public function view(User $user, VisualPlan $visualPlan): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create visual plans.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the visual plan.
     */
    public function update(User $user, VisualPlan $visualPlan): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the visual plan status.
     */
    public function updateStatus(User $user, VisualPlan $visualPlan): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the visual plan.
     */
    public function delete(User $user, VisualPlan $visualPlan): bool
    {
        return true;
    }
}
