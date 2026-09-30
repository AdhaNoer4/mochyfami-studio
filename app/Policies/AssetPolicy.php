<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\ContentProject;
use App\Models\User;

class AssetPolicy
{
    /**
     * Determine whether the user can view the assets of the given project.
     *
     * The project is passed as a policy argument instead of authorizing the
     * bare model class, because listing is always scoped to one project and
     * that project is what actually has to be authorized. Authorizing the
     * class alone would let any authenticated user read any project's asset
     * list.
     */
    public function viewAny(User $user, ?ContentProject $project = null): bool
    {
        return $this->ownsProject($user, $project);
    }

    /**
     * Determine whether the user can view the asset.
     */
    public function view(User $user, Asset $asset): bool
    {
        return $this->ownsProject($user, $asset->project);
    }

    /**
     * Determine whether the user can create assets on the given project.
     *
     * The project is passed for the same reason as in viewAny(): creating an
     * asset is a write into somebody else's project if it is not checked.
     */
    public function create(User $user, ?ContentProject $project = null): bool
    {
        return $this->ownsProject($user, $project);
    }

    /**
     * Determine whether the user can update the asset.
     */
    public function update(User $user, Asset $asset): bool
    {
        return $this->ownsProject($user, $asset->project);
    }

    /**
     * Determine whether the user can delete the asset.
     */
    public function delete(User $user, Asset $asset): bool
    {
        return $this->ownsProject($user, $asset->project);
    }

    /**
     * An asset is reachable only through its project's creator.
     *
     * A missing project, or a project without a creator, is owned by nobody
     * and therefore denies everyone. That is deliberately fail closed: an
     * absent owner is a data problem, and guessing an owner would be worse
     * than refusing access until the row is repaired.
     */
    private function ownsProject(User $user, ?ContentProject $project): bool
    {
        return $project instanceof ContentProject
            && $project->created_by !== null
            && $project->created_by === $user->id;
    }
}
