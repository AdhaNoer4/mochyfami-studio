<?php

namespace App\Services\AI\Prompt;

/**
 * The small, versionable MochyFami channel style guide.
 *
 * This profile is provider-independent and consumed by providers to shape
 * their output. It must stay small: no giant prompt framework here.
 */
class MochyFamiScriptProfile
{
    public const PROFILE = 'mochyfami_script';

    public const VERSION = 'v1';

    public const NAME = self::PROFILE.'_'.self::VERSION;

    /**
     * The established channel style for the default provider.
     *
     * @var array<int, string>
     */
    public const GUIDANCE = [
        'Write in Indonesian.',
        'Casual and educational tone; funny when it fits naturally.',
        'TTS-friendly short sentences, roughly 25-35 seconds, one main idea.',
        'No unnecessary "Halo guys" opener.',
        'Avoid excessive rhetorical filler.',
        'Never invent facts.',
        'Do not contradict supported research; flag uncertainty instead.',
        'Concise closing.',
        'Human review remains required; treat this output as a draft.',
    ];
}
