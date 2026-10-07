<?php

namespace Tests\Unit;

use App\Exceptions\TtsProviderException;
use App\Services\TTS\Providers\FakeTtsProvider;
use App\Services\TTS\TtsProviderRegistry;
use Tests\TestCase;

class TtsProviderRegistryTest extends TestCase
{
    public function test_names_and_has(): void
    {
        $registry = new TtsProviderRegistry(['fake' => FakeTtsProvider::class]);

        $this->assertSame(['fake'], $registry->names());
        $this->assertTrue($registry->has('fake'));
        $this->assertFalse($registry->has('elevenlabs'));
    }

    public function test_resolves_class_string(): void
    {
        $registry = new TtsProviderRegistry(['fake' => FakeTtsProvider::class]);

        $this->assertInstanceOf(FakeTtsProvider::class, $registry->resolve('fake'));
        $this->assertSame('fake', $registry->resolve('fake')->name());
    }

    public function test_resolves_shared_instance(): void
    {
        $fake = new FakeTtsProvider;
        $registry = new TtsProviderRegistry(['fake' => $fake]);

        $this->assertSame($fake, $registry->resolve('fake'));
    }

    public function test_unknown_provider_throws_controlled_exception(): void
    {
        $registry = new TtsProviderRegistry;

        try {
            $registry->resolve('unknown');
            $this->fail('Expected TtsProviderException to be thrown.');
        } catch (TtsProviderException $e) {
            $this->assertSame(TtsProviderException::UNKNOWN_PROVIDER, $e->getCode());
        }
    }

    public function test_misconfigured_provider_throws_controlled_exception(): void
    {
        $registry = new TtsProviderRegistry(['broken' => \stdClass::class]);

        try {
            $registry->resolve('broken');
            $this->fail('Expected TtsProviderException to be thrown.');
        } catch (TtsProviderException $e) {
            $this->assertSame(TtsProviderException::INVALID_RESPONSE, $e->getCode());
        }
    }

    public function test_default_provider_comes_from_config_and_resolves(): void
    {
        $registry = new TtsProviderRegistry(config('tts.providers'));

        $this->assertSame('fake', config('tts.default_provider'));
        $this->assertInstanceOf(FakeTtsProvider::class, $registry->resolve(config('tts.default_provider')));
    }
}
