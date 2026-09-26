<?php

namespace Tests\Feature;

use App\Enums\ResearchClaimImportance;
use App\Enums\ResearchClaimStatus;
use App\Enums\ScriptStatus;
use App\Models\ContentProject;
use App\Models\ResearchClaim;
use App\Models\ResearchReport;
use App\Models\Script;
use App\Models\Source;
use App\Models\User;
use App\Services\ScriptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScriptQualityApiTest extends TestCase
{
    use RefreshDatabase;

    private function readyReviewSetup(bool $aligned = true): array
    {
        $user = User::factory()->create();
        $project = ContentProject::factory()->for($user, 'creator')->create();
        $report = ResearchReport::factory()->create(['content_project_id' => $project->id]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $claim = ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'claim' => 'Cats purr to communicate with humans',
            'status' => ResearchClaimStatus::Supported,
            'importance' => ResearchClaimImportance::High,
        ]);
        $claim->sources()->attach($source->id);

        $service = app(ScriptService::class);
        $service->createScript($project, $aligned
            ? [
                'title' => 'Why Cats Purr',
                'hook' => 'Why do cats purr?',
                'body' => "Cats purr to communicate with humans, and it soothes them.\n",
                'closing' => 'Now you know why cats purr.',
                'duration_seconds' => 45,
                'notes' => null,
            ]
            : [
                'title' => 'Why Cats Purr',
                'hook' => 'Why do cats purr?',
                'body' => "Lorem ipsum dolor sit amet.\n",
                'closing' => 'Now you know.',
                'duration_seconds' => 45,
                'notes' => null,
            ]);
        $service->transitionStatus($project, ScriptStatus::Review);

        return [$user, $project];
    }

    public function test_guest_is_redirected_to_login(): void
    {
        Http::preventStrayRequests();
        [, $project] = $this->readyReviewSetup();

        $this->getJson("/api/v1/projects/{$project->id}/script/quality")
            ->assertStatus(401);
    }

    public function test_returns_ready_quality_payload(): void
    {
        [$user, $project] = $this->readyReviewSetup();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/projects/{$project->id}/script/quality")
            ->assertOk()
            ->assertJsonPath('data.ready', true)
            ->assertJsonPath('data.score', 100)
            ->assertJsonPath('data.status', 'review')
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.summary.blocker_count', 0)
            ->assertJsonStructure([
                'data' => [
                    'script_id',
                    'version_id',
                    'version',
                    'status',
                    'ready',
                    'score',
                    'summary' => [
                        'total_checks',
                        'passed_checks',
                        'failed_checks',
                        'blocker_count',
                        'warning_count',
                        'important_claims',
                        'aligned_important_claims',
                    ],
                    'blockers',
                    'warnings',
                    'checks' => [
                        '*' => [
                            'code',
                            'severity',
                            'passed',
                            'message',
                            'details',
                        ],
                    ],
                    'claim_alignment' => [
                        '*' => [
                            'claim_id',
                            'importance',
                            'status',
                            'matched',
                            'match_state',
                            'match_score',
                            'message',
                        ],
                    ],
                ],
            ]);
    }

    public function test_returns_blockers_message_for_draft_script(): void
    {
        [$user] = $this->readyReviewSetup();
        $project = $user->projects()->first();
        $service = app(ScriptService::class);
        $service->transitionStatus($project, ScriptStatus::Draft);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/projects/{$project->id}/script/quality")
            ->assertOk();

        $codes = collect($response->json('data.blockers'))->pluck('code')->all();
        $this->assertContains('SCRIPT_STATUS_NOT_REVIEWABLE', $codes);
        $this->assertFalse($response->json('data.ready'));
    }

    public function test_returns_blocker_when_high_claim_is_not_aligned(): void
    {
        [$user, $project] = $this->readyReviewSetup(false);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/projects/{$project->id}/script/quality")
            ->assertOk();

        $codes = collect($response->json('data.blockers'))->pluck('code')->all();
        $this->assertContains('IMPORTANT_CLAIM_NOT_ALIGNED', $codes);
        $this->assertFalse($response->json('data.ready'));
        $this->assertSame('not_detected', $response->json('data.claim_alignment.0.match_state'));
    }

    public function test_returns_blocker_when_script_has_no_current_version(): void
    {
        $user = User::factory()->create();
        $project = ContentProject::factory()->for($user, 'creator')->create();
        Script::factory()->create(['content_project_id' => $project->id, 'status' => ScriptStatus::Draft]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/projects/{$project->id}/script/quality")
            ->assertOk();

        $codes = collect($response->json('data.blockers'))->pluck('code')->all();
        $this->assertContains('NO_CURRENT_VERSION', $codes);
        $this->assertNull($response->json('data.version_id'));
        $this->assertFalse($response->json('data.ready'));
    }

    public function test_returns_404_when_project_has_no_script(): void
    {
        $user = User::factory()->create();
        $project = ContentProject::factory()->for($user, 'creator')->create();

        $this->actingAs($user)
            ->getJson("/api/v1/projects/{$project->id}/script/quality")
            ->assertNotFound()
            ->assertJsonPath('message', 'Script not found for this project.');
    }

    public function test_other_users_can_view_quality(): void
    {
        [$owner, $project] = $this->readyReviewSetup();
        $visitor = User::factory()->create();

        $this->actingAs($visitor)
            ->getJson("/api/v1/projects/{$project->id}/script/quality")
            ->assertOk()
            ->assertJsonPath('data.ready', true);
    }

    public function test_access_does_not_mutate_database(): void
    {
        [$user, $project] = $this->readyReviewSetup();
        $script = $project->script()->first();

        $countsBefore = [
            'scripts' => DB::table('scripts')->count(),
            'versions' => DB::table('script_versions')->count(),
        ];
        $statusBefore = $script->status->value;

        $this->actingAs($user)
            ->getJson("/api/v1/projects/{$project->id}/script/quality")
            ->assertOk();

        $this->assertSame($countsBefore['scripts'], DB::table('scripts')->count());
        $this->assertSame($countsBefore['versions'], DB::table('script_versions')->count());
        $this->assertSame($statusBefore, $script->fresh()->status->value);
    }

    public function test_quality_request_makes_no_external_http_calls(): void
    {
        Http::preventStrayRequests();
        [$user, $project] = $this->readyReviewSetup();

        $this->actingAs($user)
            ->getJson("/api/v1/projects/{$project->id}/script/quality")
            ->assertOk();
    }

    public function test_quality_request_uses_bounded_query_count(): void
    {
        [$user, $project] = $this->readyReviewSetup();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($user)
            ->getJson("/api/v1/projects/{$project->id}/script/quality")
            ->assertOk();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(10, $queryCount);
    }
}
