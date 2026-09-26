<?php

namespace Tests\Unit;

use App\Services\Script\ScriptTextNormalizer;
use Tests\TestCase;

class ScriptTextNormalizerTest extends TestCase
{
    private function normalizer(): ScriptTextNormalizer
    {
        return new ScriptTextNormalizer;
    }

    public function test_normalizes_to_lowercase_and_collapses_whitespace(): void
    {
        $this->assertSame('cats purr', $this->normalizer()->normalizeText("  CATS   Purr \n "));
    }

    public function test_removes_punctuation(): void
    {
        $this->assertSame('halo dunia test', $this->normalizer()->normalizeText('Halo, dunia! [test].'));
    }

    public function test_tokenize_removes_stopwords_and_short_tokens(): void
    {
        $this->assertSame(
            ['kucing', 'mendengkur', 'melompat'],
            $this->normalizer()->tokenize('Kucing yang mendengkur dan melompat')
        );
    }

    public function test_tokenize_returns_empty_for_empty_input(): void
    {
        $this->assertSame([], $this->normalizer()->tokenize(''));
    }

    public function test_tokenize_returns_empty_for_only_stopwords(): void
    {
        $this->assertSame([], $this->normalizer()->tokenize('Yang dan ini itu'));
    }

    public function test_count_words_counts_all_normalized_words(): void
    {
        $this->assertSame(3, $this->normalizer()->countWords('Ini adalah kucing'));
        $this->assertSame(0, $this->normalizer()->countWords('  '));
    }

    public function test_combined_script_text_joins_fields_and_skips_empty(): void
    {
        $this->assertSame(
            'hook body closing',
            $this->normalizer()->combinedScriptText('hook', 'body', 'closing')
        );
        $this->assertSame(
            'body',
            $this->normalizer()->combinedScriptText('', 'body', null)
        );
        $this->assertSame('', $this->normalizer()->combinedScriptText('', '', ''));
    }
}
