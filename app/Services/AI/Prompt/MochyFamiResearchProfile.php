<?php

namespace App\Services\AI\Prompt;

class MochyFamiResearchProfile
{
    public const PROFILE = 'mochyfami_research';

    public const VERSION = 'v1';

    public const NAME = self::PROFILE.'_'.self::VERSION;

    /**
     * The research philosophy the provider prompt is expected to honour.
     *
     * This is guidance for prompt/profile construction and for inspecting
     * provider output. It is not a verification mechanism — the human
     * remains responsible for fact-checking AI research candidates.
     */
    public const GUIDANCE = <<<'TEXT'
MochyFami research is produced for Indonesian-language educational content
designed for YouTube Shorts.

Research candidates must be:
- concise and factual
- useful for video content
- written so facts and uncertainty stay clearly separated
- kept free of storytelling fluff and clickbait framing

Research candidates must NEVER:
- pretend AI knowledge is a real source
- invent or fabricate a fact, number, citation, URL or publication date
- attach evidence where none was actually verified
- claim that anything has been verified, proven, or confirmed
TEXT;
}
