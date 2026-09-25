<?php

namespace App\Policies;

use App\Models\ResearchReport;
use App\Models\User;

class ResearchReportPolicy
{
    /**
     * Determine whether the user can view any research reports.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the research report.
     */
    public function view(User $user, ResearchReport $report): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create research reports.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the research report.
     */
    public function update(User $user, ResearchReport $report): bool
    {
        return true;
    }

    /**
     * Determine whether the user can transition the research report status.
     */
    public function updateStatus(User $user, ResearchReport $report): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the research report.
     */
    public function delete(User $user, ResearchReport $report): bool
    {
        return true;
    }
}
