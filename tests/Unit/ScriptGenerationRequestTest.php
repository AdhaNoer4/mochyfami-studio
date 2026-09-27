<?php

namespace Tests\Unit;

use App\Services\AI\DTO\ScriptGenerationRequest;
use App\Services\AI\Prompt\MochyFamiScriptProfile;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ScriptGenerationRequestTest extends TestCase
{
    public function test_trims_topic_and_applies_defaults(): void
    {
        $request = new ScriptGenerationRequest('  Kenapa kucing suka kardus?  ');

        $this->assertSame('Kenapa kucing suka kardus?', $request->topic);
        $this->assertSame('id', $request->language);
        $this->assertSame('casual', $request->tone);
        $this->assertSame('educational', $request->format);
        $this->assertSame(ScriptGenerationRequest::DEFAULT_DURATION_SECONDS, $request->targetDurationSeconds);
        $this->assertSame(MochyFamiScriptProfile::NAME, $request->promptProfile);
    }

    public function test_blank_optionals_fall_back_to_defaults(): void
    {
        $request = new ScriptGenerationRequest('Topic', language: '', tone: '', format: '', hookStyle: '   ');

        $this->assertSame('id', $request->language);
        $this->assertSame('casual', $request->tone);
        $this->assertSame('educational', $request->format);
        $this->assertNull($request->hookStyle);
        $this->assertNull($request->instructions);
    }

    public function test_topic_must_not_be_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ScriptGenerationRequest('   ');
    }

    public function test_topic_must_not_exceed_max_length(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ScriptGenerationRequest(str_repeat('a', ScriptGenerationRequest::MAX_TOPIC_LENGTH + 1));
    }

    public function test_language_must_not_exceed_max_length(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ScriptGenerationRequest('Topic', language: str_repeat('a', ScriptGenerationRequest::MAX_LANGUAGE_LENGTH + 1));
    }

    public function test_hook_style_must_not_exceed_max_length(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ScriptGenerationRequest('Topic', hookStyle: str_repeat('a', ScriptGenerationRequest::MAX_HOOK_STYLE_LENGTH + 1));
    }

    public function test_target_duration_must_be_within_range(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ScriptGenerationRequest('Topic', targetDurationSeconds: ScriptGenerationRequest::MAX_DURATION_SECONDS + 1);
    }

    public function test_important_claims_are_hard_capped(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ScriptGenerationRequest('Topic', importantClaims: array_fill(0, ScriptGenerationRequest::MAX_IMPORTANT_CLAIMS + 1, 'Klaim.'));
    }

    public function test_important_claims_must_be_non_empty_strings(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ScriptGenerationRequest('Topic', importantClaims: ['valid', 123]);
    }
}
