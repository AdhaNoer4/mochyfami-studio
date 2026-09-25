<?php

namespace App\Providers;

use App\Models\ContentIdea;
use App\Models\ContentProject;
use App\Models\ResearchClaim;
use App\Models\ResearchReport;
use App\Models\Source;
use App\Policies\IdeaPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\ResearchClaimPolicy;
use App\Policies\ResearchReportPolicy;
use App\Policies\ResearchSourcePolicy;
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
        Gate::policy(ResearchReport::class, ResearchReportPolicy::class);
        Gate::policy(ResearchClaim::class, ResearchClaimPolicy::class);
        Gate::policy(Source::class, ResearchSourcePolicy::class);
    }
}
