<?php

namespace App\Providers;

use App\Models\ContentIdea;
use App\Models\ContentProject;
use App\Policies\IdeaPolicy;
use App\Policies\ProjectPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(ContentIdea::class, IdeaPolicy::class);
        Gate::policy(ContentProject::class, ProjectPolicy::class);
    }
}
