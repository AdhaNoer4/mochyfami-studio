<?php

namespace App\Services;

use App\Models\ContentCategory;
use App\Models\ContentIdea;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class IdeaImportService
{
    protected array $requiredHeaders = ['title', 'category', 'hook', 'concept', 'format'];

    protected array $allowedFormats = [
        'educational', 'funny_fact', 'storytelling', 'comparison', 'pov', 'list',
    ];

    protected array $allowedStatuses = [
        'idea', 'selected', 'converted', 'archived',
    ];

    /**
     * Preview CSV file upload and parse/validate rows.
     *
     * @throws InvalidArgumentException
     */
    public function previewCsv(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        $handle = fopen($path, 'r');

        if (! $handle) {
            throw new InvalidArgumentException('Unable to open uploaded CSV file.');
        }

        // Read header
        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);
            throw new InvalidArgumentException('The uploaded CSV file is empty.');
        }

        // Clean & normalize header columns
        $headerColumns = array_map(function ($col) {
            $cleaned = strtolower(trim($col));

            // Remove UTF-8 BOM if present
            return preg_replace('/[\x00-\x1F\x7F\xEF\xBB\xBF]/', '', $cleaned);
        }, $header);

        // Check required header columns
        foreach ($this->requiredHeaders as $req) {
            if (! in_array($req, $headerColumns, true)) {
                fclose($handle);
                throw new InvalidArgumentException("The CSV is missing required column: {$req}");
            }
        }

        // Pre-fetch all active categories for fast matching
        $categories = ContentCategory::all();

        $rows = [];
        $csvSeenSlugs = [];
        $rowNumber = 1; // Row 1 is header
        $totalRows = 0;
        $validCount = 0;
        $invalidCount = 0;
        $duplicateCount = 0;

        while (($rowRaw = fgetcsv($handle)) !== false) {
            // Skip empty trailing rows
            if (count(array_filter($rowRaw)) === 0) {
                continue;
            }

            $rowNumber++;
            $totalRows++;

            if ($totalRows > 500) {
                fclose($handle);
                throw new InvalidArgumentException('CSV exceeds maximum limit of 500 rows.');
            }

            // Combine header with row values
            $rowMap = [];
            foreach ($headerColumns as $idx => $colName) {
                $rowMap[$colName] = isset($rowRaw[$idx]) ? $this->sanitizeCellValue($rowRaw[$idx]) : '';
            }

            $errors = [];
            $warnings = [];

            // 1. Validate required fields
            $title = trim($rowMap['title'] ?? '');
            $categoryInput = trim($rowMap['category'] ?? '');
            $hook = trim($rowMap['hook'] ?? '');
            $concept = trim($rowMap['concept'] ?? '');
            $formatInput = strtolower(trim($rowMap['format'] ?? ''));
            $statusInput = strtolower(trim($rowMap['status'] ?? 'idea'));
            $priorityInput = strtolower(trim($rowMap['priority'] ?? 'medium'));
            $notes = trim($rowMap['notes'] ?? '');
            $sourceIdea = trim($rowMap['source_idea'] ?? '');

            if (empty($title)) {
                $errors[] = 'Title is required.';
            } elseif (mb_strlen($title) > 255) {
                $errors[] = 'Title must not exceed 255 characters.';
            }

            if (empty($categoryInput)) {
                $errors[] = 'Category is required.';
            }

            if (empty($hook)) {
                $errors[] = 'Opening hook is required.';
            }

            if (empty($concept)) {
                $errors[] = 'Core concept is required.';
            }

            if (empty($formatInput)) {
                $errors[] = 'Content format is required.';
            } elseif (! in_array($formatInput, $this->allowedFormats, true)) {
                $errors[] = "Format \"{$formatInput}\" is invalid. Allowed: ".implode(', ', $this->allowedFormats);
            }

            // Status validation & fallback
            if (empty($statusInput)) {
                $statusInput = 'idea';
            } elseif (! in_array($statusInput, $this->allowedStatuses, true)) {
                $errors[] = "Status \"{$statusInput}\" is invalid. Allowed: ".implode(', ', $this->allowedStatuses);
            }

            // Priority normalization (low=1, medium=2, high=3)
            $priorityVal = match ($priorityInput) {
                '3', 'high' => 3,
                '1', 'low' => 1,
                default => 2,
            };

            // Category Resolution (match by slug or normalized name)
            $categoryId = null;
            if (! empty($categoryInput)) {
                $categorySlug = Str::slug($categoryInput);
                $matchedCat = $categories->first(function ($c) use ($categoryInput, $categorySlug) {
                    return strtolower($c->name) === strtolower($categoryInput) || $c->slug === $categorySlug;
                });

                if ($matchedCat) {
                    $categoryId = $matchedCat->id;
                } else {
                    $errors[] = "Category \"{$categoryInput}\" does not exist.";
                }
            }

            // Slug generation & duplicate detection
            $slug = Str::slug($title);

            if (! empty($title)) {
                // Check CSV duplicate
                if (in_array($slug, $csvSeenSlugs, true)) {
                    $warnings[] = 'Duplicate idea title detected inside CSV file.';
                    $duplicateCount++;
                } else {
                    $csvSeenSlugs[] = $slug;
                }

                // Check DB duplicate
                $dbExists = ContentIdea::where('slug', $slug)
                    ->orWhere('title', $title)
                    ->exists();

                if ($dbExists) {
                    $warnings[] = 'An idea with a similar title already exists in the database.';
                    if (! in_array($slug, $csvSeenSlugs, true)) {
                        $duplicateCount++;
                    }
                }
            }

            $isInvalid = count($errors) > 0;
            if ($isInvalid) {
                $invalidCount++;
                $status = 'invalid';
            } elseif (count($warnings) > 0) {
                $status = 'duplicate';
                $validCount++;
            } else {
                $status = 'valid';
                $validCount++;
            }

            $rows[] = [
                'row_number' => $rowNumber,
                'data' => [
                    'title' => $title,
                    'slug' => $slug,
                    'category_id' => $categoryId,
                    'category_name' => $categoryInput,
                    'hook' => $hook,
                    'concept' => $concept,
                    'format' => $formatInput,
                    'status' => $statusInput,
                    'priority' => $priorityVal,
                    'notes' => $notes,
                    'source_idea' => $sourceIdea,
                ],
                'status' => $status,
                'errors' => $errors,
                'warnings' => $warnings,
            ];
        }

        fclose($handle);

        return [
            'total_rows' => $totalRows,
            'valid_rows_count' => $validCount,
            'invalid_rows_count' => $invalidCount,
            'duplicate_rows_count' => $duplicateCount,
            'rows' => $rows,
        ];
    }

    /**
     * Execute transactional bulk import of valid rows.
     */
    public function executeImport(array $rows, int $userId): array
    {
        $importedCount = 0;
        $skippedCount = 0;
        $duplicateCount = 0;
        $failedCount = 0;

        DB::transaction(function () use ($rows, $userId, &$importedCount, &$skippedCount, &$duplicateCount, &$failedCount) {
            foreach ($rows as $item) {
                $data = $item['data'] ?? [];
                $rowStatus = $item['status'] ?? 'invalid';

                if ($rowStatus === 'invalid' || empty($data['title']) || empty($data['category_id'])) {
                    $skippedCount++;

                    continue;
                }

                if ($rowStatus === 'duplicate') {
                    $duplicateCount++;
                }

                try {
                    ContentIdea::create([
                        'title' => $data['title'],
                        'slug' => $data['slug'] ?? Str::slug($data['title']),
                        'category_id' => $data['category_id'],
                        'hook' => $data['hook'] ?? null,
                        'concept' => $data['concept'] ?? null,
                        'format' => $data['format'] ?? 'educational',
                        'status' => $data['status'] ?? 'idea',
                        'priority' => $data['priority'] ?? 2,
                        'notes' => $data['notes'] ?? null,
                        'source_idea' => $data['source_idea'] ?? null,
                        'created_by' => $userId,
                    ]);

                    $importedCount++;
                } catch (\Throwable $e) {
                    $failedCount++;
                    throw $e; // Trigger DB transaction rollback
                }
            }
        });

        return [
            'total_rows' => count($rows),
            'imported_rows' => $importedCount,
            'skipped_rows' => $skippedCount,
            'duplicate_rows' => $duplicateCount,
            'failed_rows' => $failedCount,
        ];
    }

    /**
     * Prevent CSV Formula Injection (=, +, -, @).
     */
    protected function sanitizeCellValue(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@'], true)) {
            return "'".$trimmed; // Prefix with single quote to sanitize formula injection
        }

        return $trimmed;
    }
}
