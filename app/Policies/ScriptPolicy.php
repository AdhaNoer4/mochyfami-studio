<?php

namespace App\Policies;

use App\Models\Script;
use App\Models\User;

class ScriptPolicy
{
    /**
     * Determine whether the user can view the script.
     */
    public function view(User $user, Script $script): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create scripts.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the script.
     */
    public function update(User $user, Script $script): bool
    {
        return true;
    }

    /**
     * Determine whether the user can transition the script status.
     */
    public function updateStatus(User $user, Script $script): bool
    {
        return true;
    }
}
