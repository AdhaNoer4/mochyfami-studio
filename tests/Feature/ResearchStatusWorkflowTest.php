<?php

namespace Tests\Feature;

use App\Enums\ResearchStatus;
use App\Models\ContentProject;
use App\Models\ResearchReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ResearchStatusWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function makeReport(ResearchStatus $status): ResearchReport
    {
        $project = ContentProject::factory()->create();

        return ResearchReport::factory()->create([
            'content_project_id' => $project->id,
            'status' => $status,
        ]);
    }

    private function transition(ResearchReport $report, ResearchStatus $target): TestResponse
    {
        return $this->actingAs($this->user, 'sanctum')
            ->patchJson(
                "/api/v1/projects/{$report->content_project_id}/research/status",
                ['status' => $target->value]
            );
    }

    public static function allowedTransitionsProvider(): array
    {
        return [
            'pending to researching' => [ResearchStatus::Pending, ResearchStatus::Researching],
            'researching to completed' => [ResearchStatus::Researching, ResearchStatus::Completed],
            'researching to needs review' => [ResearchStatus::Researching, ResearchStatus::NeedsReview],
            'researching to failed' => [ResearchStatus::Researching, ResearchStatus::Failed],
            'completed to needs review' => [ResearchStatus::Completed, ResearchStatus::NeedsReview],
            'needs review to completed' => [ResearchStatus::NeedsReview, ResearchStatus::Completed],
            'needs review to researching' => [ResearchStatus::NeedsReview, ResearchStatus::Researching],
            'needs review to failed' => [ResearchStatus::NeedsReview, ResearchStatus::Failed],
            'failed to pending' => [ResearchStatus::Failed, ResearchStatus::Pending],
            'failed to researching' => [ResearchStatus::Failed, ResearchStatus::Researching],
        ];
    }

    #[DataProvider('allowedTransitionsProvider')]
    public function test_allows_valid_status_transition(ResearchStatus $from, ResearchStatus $to): void
    {
        $report = $this->makeReport($from);

        $response = $this->transition($report, $to);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Research status updated successfully.')
            ->assertJsonPath('data.id', $report->id)
            ->assertJsonPath('data.status', $to->value);

        $this->assertDatabaseHas('research_reports', [
            'id' => $report->id,
            'status' => $to->value,
        ]);
    }

    public static function invalidTransitionsProvider(): array
    {
        return [
            'pending to completed' => [ResearchStatus::Pending, ResearchStatus::Completed],
            'pending to failed' => [ResearchStatus::Pending, ResearchStatus::Failed],
            'pending to needs review' => [ResearchStatus::Pending, ResearchStatus::NeedsReview],
            'researching to pending' => [ResearchStatus::Researching, ResearchStatus::Pending],
            'completed to pending' => [ResearchStatus::Completed, ResearchStatus::Pending],
            'completed to researching' => [ResearchStatus::Completed, ResearchStatus::Researching],
            'completed to failed' => [ResearchStatus::Completed, ResearchStatus::Failed],
            'needs review to pending' => [ResearchStatus::NeedsReview, ResearchStatus::Pending],
            'failed to completed' => [ResearchStatus::Failed, ResearchStatus::Completed],
            'failed to needs review' => [ResearchStatus::Failed, ResearchStatus::NeedsReview],
        ];
    }

    #[DataProvider('invalidTransitionsProvider')]
    public function test_rejects_invalid_status_transition_with_422(
        ResearchStatus $from,
        ResearchStatus $to
    ): void {
        $report = $this->makeReport($from);

        $response = $this->transition($report, $to);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'message',
                "Cannot transition research status from '{$from->value}' to '{$to->value}'."
            );

        $this->assertDatabaseHas('research_reports', [
            'id' => $report->id,
            'status' => $from->value,
        ]);
    }

    public static function sameStatusProvider(): array
    {
        return [
            'pending to pending' => [ResearchStatus::Pending],
            'researching to researching' => [ResearchStatus::Researching],
            'completed to completed' => [ResearchStatus::Completed],
            'failed to failed' => [ResearchStatus::Failed],
        ];
    }

    #[DataProvider('sameStatusProvider')]
    public function test_rejects_same_status_transition_with_422(ResearchStatus $status): void
    {
        $report = $this->makeReport($status);

        $response = $this->transition($report, $status);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'message',
                "Cannot transition research status from '{$status->value}' to '{$status->value}'."
            );

        $this->assertDatabaseHas('research_reports', [
            'id' => $report->id,
            'status' => $status->value,
        ]);
    }

    public function test_rejects_unknown_status_value_with_422(): void
    {
        $report = $this->makeReport(ResearchStatus::Pending);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson(
                "/api/v1/projects/{$report->content_project_id}/research/status",
                ['status' => 'not_a_status']
            );

        $response->assertStatus(422)->assertJsonValidationErrors(['status']);
    }

    public function test_transition_requires_existing_report(): void
    {
        $project = ContentProject::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/research/status", ['status' => 'researching']);

        $response->assertNotFound();
    }

    public function test_rejects_unauthenticated_request_with_401(): void
    {
        $report = $this->makeReport(ResearchStatus::Pending);

        $response = $this->patchJson(
            "/api/v1/projects/{$report->content_project_id}/research/status",
            ['status' => 'researching']
        );

        $response->assertStatus(401);
    }

    public function test_pending_report_exposes_only_valid_allowed_transitions(): void
    {
        $report = $this->makeReport(ResearchStatus::Pending);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$report->content_project_id}/research");

        $response->assertOk()
            ->assertJsonCount(1, 'data.allowed_transitions')
            ->assertJsonPath('data.allowed_transitions.0.status', 'researching')
            ->assertJsonPath('data.allowed_transitions.0.action', 'Start Research')
            ->assertJsonPath('data.allowed_transitions.0.destructive', false);
    }

    public function test_researching_report_exposes_destructive_failed_transition(): void
    {
        $report = $this->makeReport(ResearchStatus::Researching);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$report->content_project_id}/research");

        $response->assertOk()
            ->assertJsonCount(3, 'data.allowed_transitions')
            ->assertJsonPath('data.allowed_transitions.2.status', 'failed')
            ->assertJsonPath('data.allowed_transitions.2.action', 'Mark Failed')
            ->assertJsonPath('data.allowed_transitions.2.destructive', true);
    }
}
