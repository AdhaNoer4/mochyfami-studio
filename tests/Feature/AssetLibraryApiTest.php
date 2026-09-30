<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\ContentProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Listing behaviour for the Asset Library: pagination, search, filters, and
 * sorting, plus the project scoping each of those depends on.
 *
 * The scope tests exist because search, filtering, and ordering are three
 * separate places where a project constraint could be dropped. A leak through
 * any one of them would let a user read another project's asset titles through
 * their own project, so each is exercised on its own.
 */
class AssetLibraryApiTest extends TestCase
{
    use RefreshDatabase;

    private function projectFor(User $user): ContentProject
    {
        return ContentProject::factory()->for($user, 'creator')->create();
    }

    private function listUrl(ContentProject $project, array $query = []): string
    {
        $url = "/api/v1/projects/{$project->id}/assets";

        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        return $url;
    }

    /**
     * @return array<int, int>
     */
    private function listedIds(TestResponse $response): array
    {
        return array_column($response->json('data.items'), 'id');
    }

    // -----------------------------------------------------------------
    // Pagination
    // -----------------------------------------------------------------

    #[Test]
    public function test_index_returns_the_pagination_envelope(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        Asset::factory()->count(3)->create(['content_project_id' => $project->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->listUrl($project))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data.items')
            ->assertJsonPath('data.pagination.total', 3)
            ->assertJsonPath('data.pagination.per_page', 10)
            ->assertJsonPath('data.pagination.current_page', 1)
            ->assertJsonPath('data.pagination.last_page', 1);
    }

    #[Test]
    public function test_index_honours_a_custom_per_page(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        Asset::factory()->count(30)->create(['content_project_id' => $project->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->listUrl($project, ['per_page' => 25]))
            ->assertOk()
            ->assertJsonCount(25, 'data.items')
            ->assertJsonPath('data.pagination.per_page', 25)
            ->assertJsonPath('data.pagination.total', 30)
            ->assertJsonPath('data.pagination.last_page', 2);
    }

    #[Test]
    public function test_index_walks_the_pages_without_repeating_or_dropping_a_row(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        // Every row ties on duration_seconds (all null) and on created_at, so
        // only the secondary id order keeps the pages stable. Without it the
        // same asset can land on two pages and another on none.
        Asset::factory()->count(12)->create([
            'content_project_id' => $project->id,
            'duration_seconds' => null,
        ]);

        $first = $this->actingAs($user, 'sanctum')
            ->getJson($this->listUrl($project, ['sort' => 'duration_seconds']));

        $second = $this->actingAs($user, 'sanctum')
            ->getJson($this->listUrl($project, ['page' => 2, 'sort' => 'duration_seconds']));

        $firstIds = $this->listedIds($first);
        $secondIds = $this->listedIds($second);

        $this->assertCount(10, $firstIds);
        $this->assertCount(2, $secondIds);
        $this->assertEmpty(
            array_intersect($firstIds, $secondIds),
            'Two assets tied on the sort field appeared on both pages.'
        );
        $this->assertCount(12, array_unique(array_merge($firstIds, $secondIds)));
    }

    #[Test]
    public function test_index_returns_an_empty_page_beyond_the_last_one(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        Asset::factory()->count(2)->create(['content_project_id' => $project->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->listUrl($project, ['page' => 9]))
            ->assertOk()
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.pagination.current_page', 9)
            ->assertJsonPath('data.pagination.total', 2);
    }

    // -----------------------------------------------------------------
    // Search
    // -----------------------------------------------------------------

    #[Test]
    public function test_index_searches_the_title(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        Asset::factory()->create(['content_project_id' => $project->id, 'title' => 'Kitten on a rug']);
        Asset::factory()->create(['content_project_id' => $project->id, 'title' => 'City traffic at night']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson($this->listUrl($project, ['search' => 'kitten']));

        $response->assertOk()->assertJsonCount(1, 'data.items');
        $this->assertSame('Kitten on a rug', $response->json('data.items.0.title'));
    }

    #[Test]
    public function test_index_searches_the_file_name_source_and_notes(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        Asset::factory()->create([
            'content_project_id' => $project->id,
            'title' => 'A', 'file_name' => 'zebra-clip.mp4', 'source_name' => null, 'notes' => null,
        ]);
        Asset::factory()->create([
            'content_project_id' => $project->id,
            'title' => 'B', 'file_name' => null, 'source_name' => 'Zebra Stock', 'notes' => null,
        ]);
        Asset::factory()->create([
            'content_project_id' => $project->id,
            'title' => 'C', 'file_name' => null, 'source_name' => null, 'notes' => 'striped like a zebra',
        ]);
        Asset::factory()->create([
            'content_project_id' => $project->id,
            'title' => 'Unrelated', 'file_name' => null, 'source_name' => null, 'notes' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson($this->listUrl($project, ['search' => 'zebra']));

        $response->assertOk()->assertJsonCount(3, 'data.items');
    }

    #[Test]
    public function test_index_ignores_an_unmatched_search(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        Asset::factory()->create(['content_project_id' => $project->id, 'title' => 'Kitten']);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->listUrl($project, ['search' => 'zzzznotfound']))
            ->assertOk()
            ->assertJsonPath('data.items', []);
    }

    // -----------------------------------------------------------------
    // Filters
    // -----------------------------------------------------------------

    #[Test]
    public function test_index_filters_by_type(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        Asset::factory()->create(['content_project_id' => $project->id, 'type' => AssetType::Video]);
        Asset::factory()->create(['content_project_id' => $project->id, 'type' => AssetType::Audio]);
        Asset::factory()->create(['content_project_id' => $project->id, 'type' => AssetType::Audio]);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->listUrl($project, ['type' => 'audio']))
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.type', 'audio')
            ->assertJsonPath('data.items.1.type', 'audio');
    }

    #[Test]
    public function test_index_filters_by_status(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        Asset::factory()->create(['content_project_id' => $project->id, 'status' => AssetStatus::Pending]);
        Asset::factory()->create(['content_project_id' => $project->id, 'status' => AssetStatus::Available]);
        Asset::factory()->create(['content_project_id' => $project->id, 'status' => AssetStatus::Available]);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->listUrl($project, ['status' => 'available']))
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.status', 'available');
    }

    // -----------------------------------------------------------------
    // Sorting
    // -----------------------------------------------------------------

    #[Test]
    public function test_index_sorts_by_title_in_both_directions(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        Asset::factory()->create(['content_project_id' => $project->id, 'title' => 'Banana']);
        Asset::factory()->create(['content_project_id' => $project->id, 'title' => 'Apple']);
        Asset::factory()->create(['content_project_id' => $project->id, 'title' => 'Cherry']);

        $ascending = $this->actingAs($user, 'sanctum')
            ->getJson($this->listUrl($project, ['sort' => 'title', 'direction' => 'asc']));
        $descending = $this->actingAs($user, 'sanctum')
            ->getJson($this->listUrl($project, ['sort' => 'title', 'direction' => 'desc']));

        $this->assertSame(
            ['Apple', 'Banana', 'Cherry'],
            array_column($ascending->json('data.items'), 'title')
        );
        $this->assertSame(
            ['Cherry', 'Banana', 'Apple'],
            array_column($descending->json('data.items'), 'title')
        );
    }

    #[Test]
    public function test_index_sorts_by_file_size(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        Asset::factory()->create(['content_project_id' => $project->id, 'file_size' => 300]);
        Asset::factory()->create(['content_project_id' => $project->id, 'file_size' => 100]);
        Asset::factory()->create(['content_project_id' => $project->id, 'file_size' => 200]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson($this->listUrl($project, ['sort' => 'file_size', 'direction' => 'asc']));

        $this->assertSame(
            [100, 200, 300],
            array_column($response->json('data.items'), 'file_size')
        );
    }

    #[Test]
    public function test_index_defaults_to_newest_first(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $older = Asset::factory()->create([
            'content_project_id' => $project->id,
            'created_at' => now()->subDays(5),
        ]);
        $newer = Asset::factory()->create([
            'content_project_id' => $project->id,
            'created_at' => now(),
        ]);

        $this->assertSame(
            [$newer->id, $older->id],
            $this->listedIds(
                $this->actingAs($user, 'sanctum')->getJson($this->listUrl($project))
            )
        );
    }

    // -----------------------------------------------------------------
    // Combined behaviour
    // -----------------------------------------------------------------

    #[Test]
    public function test_index_combines_search_filters_and_sorting(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        Asset::factory()->create([
            'content_project_id' => $project->id, 'title' => 'Cat intro clip',
            'type' => AssetType::Video, 'status' => AssetStatus::Available, 'file_size' => 900,
        ]);
        Asset::factory()->create([
            'content_project_id' => $project->id, 'title' => 'Cat outro clip',
            'type' => AssetType::Video, 'status' => AssetStatus::Available, 'file_size' => 100,
        ]);
        Asset::factory()->create([
            'content_project_id' => $project->id, 'title' => 'Cat theme song',
            'type' => AssetType::Audio, 'status' => AssetStatus::Available, 'file_size' => 500,
        ]);
        Asset::factory()->create([
            'content_project_id' => $project->id, 'title' => 'Dog intro clip',
            'type' => AssetType::Video, 'status' => AssetStatus::Available, 'file_size' => 800,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson($this->listUrl($project, [
            'search' => 'cat',
            'type' => 'video',
            'status' => 'available',
            'sort' => 'file_size',
            'direction' => 'asc',
        ]));

        $response->assertOk()->assertJsonCount(2, 'data.items');
        $this->assertSame([100, 900], array_column($response->json('data.items'), 'file_size'));
    }

    #[Test]
    public function test_index_keeps_the_pagination_totals_in_step_with_the_filters(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        Asset::factory()->count(3)->create([
            'content_project_id' => $project->id,
            'type' => AssetType::Video,
        ]);
        Asset::factory()->count(2)->create([
            'content_project_id' => $project->id,
            'type' => AssetType::Image,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->listUrl($project, ['type' => 'image']))
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.pagination.total', 2);
    }

    // -----------------------------------------------------------------
    // Query string validation
    // -----------------------------------------------------------------

    #[Test]
    public function test_index_rejects_an_unknown_type_filter(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->listUrl($project, ['type' => 'hologram']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    #[Test]
    public function test_index_rejects_an_unknown_status_filter(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->listUrl($project, ['status' => 'on_fire']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    #[Test]
    public function test_index_rejects_an_unlisted_per_page(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->listUrl($project, ['per_page' => 7]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }

    /**
     * A sort field outside the allowlist must be refused, not quietly ignored.
     *
     * Falling back to a default would hide a caller mistake, and letting the
     * value through would hand the query string a column name to hand to the
     * database. The test asserts the 422 because that is the only outcome in
     * which neither happened.
     */
    #[Test]
    public function test_index_rejects_a_sort_field_outside_the_allowlist(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->listUrl($project, ['sort' => 'file_path']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('sort');

        $this->actingAs($user, 'sanctum')
            ->getJson($this->listUrl($project, ['sort' => 'raw_sql_here']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('sort');
    }

    #[Test]
    public function test_index_rejects_an_unknown_sort_direction(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->listUrl($project, ['direction' => 'sideways']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('direction');
    }

    // -----------------------------------------------------------------
    // Project scope
    // -----------------------------------------------------------------

    #[Test]
    public function test_index_is_forbidden_for_another_users_project(): void
    {
        Http::preventStrayRequests();
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $project = $this->projectFor($owner);

        Asset::factory()->create(['content_project_id' => $project->id]);

        $this->actingAs($stranger, 'sanctum')
            ->getJson($this->listUrl($project))
            ->assertForbidden();
    }

    #[Test]
    public function test_search_cannot_reach_another_projects_assets(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $mine = $this->projectFor($user);
        $other = $this->projectFor($user);

        Asset::factory()->create([
            'content_project_id' => $other->id,
            'title' => 'Zebra crossing timelapse',
        ]);
        Asset::factory()->create([
            'content_project_id' => $mine->id,
            'title' => 'Cat naps in a sunbeam',
        ]);

        // The user owns both projects, so this is purely a scoping question and
        // not a permissions one. The term matches only in the other project.
        $response = $this->actingAs($user, 'sanctum')
            ->getJson($this->listUrl($mine, ['search' => 'zebra']));

        $response->assertOk()
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.pagination.total', 0);
    }

    #[Test]
    public function test_filters_cannot_reach_another_projects_assets(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $mine = $this->projectFor($user);
        $other = $this->projectFor($user);

        Asset::factory()->create([
            'content_project_id' => $mine->id,
            'type' => AssetType::Video,
            'status' => AssetStatus::Pending,
        ]);
        Asset::factory()->create([
            'content_project_id' => $other->id,
            'type' => AssetType::Image,
            'status' => AssetStatus::Available,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->listUrl($mine, ['type' => 'image', 'status' => 'available']))
            ->assertOk()
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.pagination.total', 0);
    }

    #[Test]
    public function test_sorting_cannot_reach_another_projects_assets(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $mine = $this->projectFor($user);
        $other = $this->projectFor($user);

        Asset::factory()->count(3)->create([
            'content_project_id' => $mine->id,
            'title' => 'Mine A',
        ]);
        Asset::factory()->count(3)->create([
            'content_project_id' => $other->id,
            'title' => 'Theirs',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson($this->listUrl($mine, ['sort' => 'title', 'direction' => 'asc']));

        $response->assertOk()
            ->assertJsonCount(3, 'data.items')
            ->assertJsonPath('data.pagination.total', 3);

        $this->assertSame(
            ['Mine A'],
            array_values(array_unique(array_column($response->json('data.items'), 'title')))
        );
    }

    #[Test]
    public function test_listing_never_exposes_the_internal_file_path(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        Asset::factory()->create([
            'content_project_id' => $project->id,
            'file_path' => 'assets/secret/location.mp4',
        ]);

        $item = $this->actingAs($user, 'sanctum')
            ->getJson($this->listUrl($project))
            ->json('data.items.0');

        $this->assertArrayNotHasKey('file_path', $item);
    }
}
