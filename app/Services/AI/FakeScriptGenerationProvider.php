<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\ScriptGenerationProvider;
use App\Services\AI\DTO\ScriptGenerationRequest;
use App\Services\AI\DTO\ScriptGenerationResponse;

/**
 * Deterministic in-memory provider for tests and local development.
 *
 * Performs zero network requests, uses no randomness and no timestamps.
 * It only proves the generation architecture works — it is not production AI.
 */
class FakeScriptGenerationProvider implements ScriptGenerationProvider
{
    public function name(): string
    {
        return 'fake';
    }

    public function generate(ScriptGenerationRequest $request): ScriptGenerationResponse
    {
        $topic = $request->topic;
        $isIndonesian = $request->language === 'id';

        return new ScriptGenerationResponse(
            provider: $this->name(),
            model: null,
            title: $this->title($topic),
            hook: $this->hook($topic, $isIndonesian),
            body: $this->body($topic, $request->importantClaims, $request->hookStyle, $isIndonesian),
            closing: $this->closing($topic, $isIndonesian),
            durationSeconds: $request->targetDurationSeconds,
            notes: 'Draft deterministik dari FakeScriptGenerationProvider; hanya untuk uji dan pengembangan lokal.',
        );
    }

    private function title(string $topic): string
    {
        return mb_convert_case($topic, MB_CASE_TITLE, 'UTF-8');
    }

    private function hook(string $topic, bool $isIndonesian): string
    {
        return $isIndonesian
            ? "Tahukah kamu fakta menarik tentang {$topic}?"
            : "Did you know a fun fact about {$topic}?";
    }

    /**
     * @param  array<int, string>  $importantClaims
     */
    private function body(string $topic, array $importantClaims, ?string $hookStyle, bool $isIndonesian): string
    {
        $lines = [];

        if ($hookStyle !== null && $hookStyle !== '') {
            $lines[] = "{$hookStyle}:";
        }

        $lines[] = $isIndonesian
            ? "Kali ini kita bahas {$topic} secara singkat dan padat."
            : "Today we briefly cover {$topic}.";

        foreach ($importantClaims as $claim) {
            $lines[] = $claim;
        }

        $lines[] = $isIndonesian
            ? 'Penjelasan sederhana membuat topik ini mudah dipahami semua orang.'
            : 'A simple explanation makes this topic easy to understand for everyone.';

        return implode("\n", $lines);
    }

    private function closing(string $topic, bool $isIndonesian): string
    {
        return $isIndonesian
            ? "Itulah inti cerita tentang {$topic}. Sampai jumpa di video berikutnya."
            : "That is the core story about {$topic}. See you in the next video.";
    }
}
