<?php

namespace Tests\Feature;

use App\Models\ContentCategory;
use App\Models\ContentIdea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class IdeaImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ContentCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->category = ContentCategory::factory()->create([
            'name' => 'Cat Behavior',
            'slug' => 'cat-behavior',
        ]);
    }

    protected function createCsvFile(string $content, string $name = 'import.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    public function test_1_valid_csv_preview(): void
    {
        $csv = "title,category,hook,concept,format,status,priority,notes\n".
            '"Why Cats Meow",Cat Behavior,"Ever wonder why cats meow?","Cat vocal communication",educational,idea,high,"Sample notes"';

        $file = $this->createCsvFile($csv);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/ideas/import/preview', ['csv_file' => $file]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_rows' => 1,
                    'valid_rows_count' => 1,
                    'invalid_rows_count' => 0,
                    'duplicate_rows_count' => 0,
                    'rows' => [
                        [
                            'row_number' => 2,
                            'status' => 'valid',
                            'data' => [
                                'title' => 'Why Cats Meow',
                                'category_name' => 'Cat Behavior',
                                'format' => 'educational',
                                'status' => 'idea',
                                'priority' => 3,
                            ],
                        ],
                    ],
                ],
            ]);
    }

    public function test_2_invalid_csv_file_type(): void
    {
        $file = UploadedFile::fake()->create('script.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/ideas/import/preview', ['csv_file' => $file]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['csv_file']);
    }

    public function test_3_missing_required_header(): void
    {
        $csv = "title,hook,concept\n".
            '"Why Cats Meow","Ever wonder why?","Concept text"';

        $file = $this->createCsvFile($csv);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/ideas/import/preview', ['csv_file' => $file]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'The CSV is missing required column: category',
            ]);
    }

    public function test_4_invalid_category_marks_row_invalid(): void
    {
        $csv = "title,category,hook,concept,format,status,priority\n".
            '"Alien Fact","Unknown Category","Hook","Concept",educational,idea,medium';

        $file = $this->createCsvFile($csv);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/ideas/import/preview', ['csv_file' => $file]);

        $response->assertStatus(200)
            ->assertJsonPath('data.rows.0.status', 'invalid')
            ->assertJsonPath('data.rows.0.errors.0', 'Category "Unknown Category" does not exist.');
    }

    public function test_5_invalid_format_marks_row_invalid(): void
    {
        $csv = "title,category,hook,concept,format,status,priority\n".
            '"Test Title",Cat Behavior,"Hook","Concept",invalid_format,idea,medium';

        $file = $this->createCsvFile($csv);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/ideas/import/preview', ['csv_file' => $file]);

        $response->assertStatus(200)
            ->assertJsonPath('data.rows.0.status', 'invalid');
    }

    public function test_6_invalid_status_marks_row_invalid(): void
    {
        $csv = "title,category,hook,concept,format,status,priority\n".
            '"Test Title",Cat Behavior,"Hook","Concept",educational,invalid_status,medium';

        $file = $this->createCsvFile($csv);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/ideas/import/preview', ['csv_file' => $file]);

        $response->assertStatus(200)
            ->assertJsonPath('data.rows.0.status', 'invalid');
    }

    public function test_7_priority_normalization(): void
    {
        $csv = "title,category,hook,concept,format,status,priority\n".
            "\"High Idea\",Cat Behavior,\"Hook\",\"Concept\",educational,idea,high\n".
            '"Low Idea",Cat Behavior,"Hook","Concept",educational,idea,low';

        $file = $this->createCsvFile($csv);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/ideas/import/preview', ['csv_file' => $file]);

        $response->assertStatus(200)
            ->assertJsonPath('data.rows.0.data.priority', 3)
            ->assertJsonPath('data.rows.1.data.priority', 1);
    }

    public function test_8_duplicate_rows_inside_csv(): void
    {
        $csv = "title,category,hook,concept,format,status,priority\n".
            "\"Duplicate Title\",Cat Behavior,\"Hook 1\",\"Concept 1\",educational,idea,medium\n".
            '"Duplicate Title",Cat Behavior,"Hook 2","Concept 2",educational,idea,medium';

        $file = $this->createCsvFile($csv);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/ideas/import/preview', ['csv_file' => $file]);

        $response->assertStatus(200)
            ->assertJsonPath('data.rows.0.status', 'valid')
            ->assertJsonPath('data.rows.1.status', 'duplicate');
    }

    public function test_9_existing_duplicate_ideas_in_db(): void
    {
        ContentIdea::factory()->create([
            'category_id' => $this->category->id,
            'title' => 'Existing DB Idea Title',
            'created_by' => $this->user->id,
        ]);

        $csv = "title,category,hook,concept,format,status,priority\n".
            '"Existing DB Idea Title",Cat Behavior,"Hook","Concept",educational,idea,medium';

        $file = $this->createCsvFile($csv);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/ideas/import/preview', ['csv_file' => $file]);

        $response->assertStatus(200)
            ->assertJsonPath('data.rows.0.status', 'duplicate');
    }

    public function test_10_empty_csv(): void
    {
        $file = $this->createCsvFile('');

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/ideas/import/preview', ['csv_file' => $file]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_11_exceeding_row_limit(): void
    {
        $content = "title,category,hook,concept,format,status,priority\n";
        for ($i = 1; $i <= 501; $i++) {
            $content .= "\"Idea {$i}\",Cat Behavior,\"Hook\",\"Concept\",educational,idea,medium\n";
        }

        $file = $this->createCsvFile($content);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/ideas/import/preview', ['csv_file' => $file]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'CSV exceeds maximum limit of 500 rows.',
            ]);
    }

    public function test_12_successful_import_execution(): void
    {
        $rows = [
            [
                'row_number' => 2,
                'status' => 'valid',
                'data' => [
                    'title' => 'Imported Idea 1',
                    'slug' => 'imported-idea-1',
                    'category_id' => $this->category->id,
                    'hook' => 'Hook text',
                    'concept' => 'Concept text',
                    'format' => 'educational',
                    'status' => 'idea',
                    'priority' => 2,
                    'notes' => 'Notes text',
                ],
            ],
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/ideas/import', ['rows' => $rows]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_rows' => 1,
                    'imported_rows' => 1,
                    'skipped_rows' => 0,
                ],
            ]);

        $this->assertDatabaseHas('content_ideas', [
            'title' => 'Imported Idea 1',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_13_partial_import_skips_invalid_rows(): void
    {
        $rows = [
            [
                'row_number' => 2,
                'status' => 'valid',
                'data' => [
                    'title' => 'Valid Row Idea',
                    'slug' => 'valid-row-idea',
                    'category_id' => $this->category->id,
                    'hook' => 'Hook',
                    'concept' => 'Concept',
                    'format' => 'educational',
                    'status' => 'idea',
                    'priority' => 2,
                ],
            ],
            [
                'row_number' => 3,
                'status' => 'invalid',
                'data' => [
                    'title' => 'Invalid Row Idea',
                    'category_id' => null,
                ],
            ],
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/ideas/import', ['rows' => $rows]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_rows' => 2,
                    'imported_rows' => 1,
                    'skipped_rows' => 1,
                ],
            ]);

        $this->assertDatabaseHas('content_ideas', ['title' => 'Valid Row Idea']);
        $this->assertDatabaseMissing('content_ideas', ['title' => 'Invalid Row Idea']);
    }

    public function test_14_unauthorized_access_returns_401(): void
    {
        $response = $this->postJson('/api/v1/ideas/import/preview');

        $response->assertStatus(401);
    }
}
