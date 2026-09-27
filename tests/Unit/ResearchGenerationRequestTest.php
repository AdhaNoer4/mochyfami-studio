<?php

namespace Tests\Unit;

use App\Services\AI\DTO\ResearchGenerationRequest;
use App\Services\AI\Prompt\MochyFamiResearchProfile;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ResearchGenerationRequestTest extends TestCase
{
    public function test_trims_text_and_applies_defaults(): void
    {
        $request = new ResearchGenerationRequest('  Kenapa kucing suka kardus?  ', '  Mengapa kucing suka kardus?  ');

        $this->assertSame('Kenapa kucing suka kardus?', $request->topic);
        $this->assertSame('Mengapa kucing suka kardus?', $request->question);
        $this->assertNull($request->context);
        $this->assertSame(ResearchGenerationRequest::DEFAULT_MAX_CLAIMS, $request->maxClaims);
        $this->assertSame(MochyFamiResearchProfile::NAME, $request->promptProfile());
    }

    public function test_blank_context_becomes_null(): void
    {
        $request = new ResearchGenerationRequest('Topic', 'Question', '   ');

        $this->assertNull($request->context);
    }

    public function test_keeps_trimmed_context(): void
    {
        $request = new ResearchGenerationRequest('Topic', 'Question', '  Extended background.  ');

        $this->assertSame('Extended background.', $request->context);
    }

    public function test_empty_topic_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ResearchGenerationRequest('   ', 'Question');
    }

    public function test_empty_question_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ResearchGenerationRequest('Topic', '  ');
    }

    public function test_overlong_question_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ResearchGenerationRequest('Topic', str_repeat('a', ResearchGenerationRequest::MAX_QUESTION_LENGTH + 1));
    }

    public function test_overlong_context_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ResearchGenerationRequest('Topic', 'Question', str_repeat('a', ResearchGenerationRequest::MAX_CONTEXT_LENGTH + 1));
    }

    public function test_max_claims_must_be_within_bounds(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ResearchGenerationRequest('Topic', 'Question', maxClaims: ResearchGenerationRequest::MAX_MAX_CLAIMS + 1);
    }
}
