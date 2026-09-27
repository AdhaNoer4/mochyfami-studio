<?php

namespace Tests\Unit;

use App\Exceptions\ScriptGenerationProviderException;
use App\Services\AI\FakeScriptGenerationProvider;
use App\Services\AI\ScriptGenerationProviderRegistry;
use PHPUnit\Framework\TestCase;

class ScriptGenerationProviderRegistryTest extends TestCase
{
    public function test_names_and_has(): void
    {
        $registry = new ScriptGenerationProviderRegistry(['fake' => FakeScriptGenerationProvider::class]);

        $this->assertSame(['fake'], $registry->names());
        $this->assertTrue($registry->has('fake'));
        $this->assertFalse($registry->has('openai'));
    }

    public function test_resolves_class_string(): void
    {
        $registry = new ScriptGenerationProviderRegistry(['fake' => FakeScriptGenerationProvider::class]);

        $this->assertInstanceOf(FakeScriptGenerationProvider::class, $registry->resolve('fake'));
        $this->assertSame('fake', $registry->resolve('fake')->name());
    }

    public function test_resolves_shared_instance(): void
    {
        $fake = new FakeScriptGenerationProvider;
        $registry = new ScriptGenerationProviderRegistry(['fake' => $fake]);

        $this->assertSame($fake, $registry->resolve('fake'));
    }

    public function test_unknown_provider_throws_controlled_exception(): void
    {
        $registry = new ScriptGenerationProviderRegistry;

        try {
            $registry->resolve('unknown');
            $this->fail('Expected ScriptGenerationProviderException to be thrown.');
        } catch (ScriptGenerationProviderException $e) {
            $this->assertSame(ScriptGenerationProviderException::UNKNOWN_PROVIDER, $e->getCode());
        }
    }

    public function test_misconfigured_provider_throws_controlled_exception(): void
    {
        $registry = new ScriptGenerationProviderRegistry(['broken' => \stdClass::class]);

        try {
            $registry->resolve('broken');
            $this->fail('Expected ScriptGenerationProviderException to be thrown.');
        } catch (ScriptGenerationProviderException $e) {
            $this->assertSame(ScriptGenerationProviderException::INVALID_RESPONSE, $e->getCode());
        }
    }
}
