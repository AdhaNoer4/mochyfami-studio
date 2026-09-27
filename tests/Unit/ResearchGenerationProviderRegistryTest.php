<?php

namespace Tests\Unit;

use App\Exceptions\ResearchGenerationProviderException;
use App\Services\AI\FakeResearchGenerationProvider;
use App\Services\AI\ResearchGenerationProviderRegistry;
use PHPUnit\Framework\TestCase;

class ResearchGenerationProviderRegistryTest extends TestCase
{
    public function test_names_and_has(): void
    {
        $registry = new ResearchGenerationProviderRegistry(['fake' => FakeResearchGenerationProvider::class]);

        $this->assertSame(['fake'], $registry->names());
        $this->assertTrue($registry->has('fake'));
        $this->assertFalse($registry->has('openai'));
    }

    public function test_resolves_class_string(): void
    {
        $registry = new ResearchGenerationProviderRegistry(['fake' => FakeResearchGenerationProvider::class]);

        $this->assertInstanceOf(FakeResearchGenerationProvider::class, $registry->resolve('fake'));
        $this->assertSame('fake', $registry->resolve('fake')->name());
    }

    public function test_resolves_shared_instance(): void
    {
        $fake = new FakeResearchGenerationProvider;
        $registry = new ResearchGenerationProviderRegistry(['fake' => $fake]);

        $this->assertSame($fake, $registry->resolve('fake'));
    }

    public function test_unknown_provider_throws_controlled_exception(): void
    {
        $registry = new ResearchGenerationProviderRegistry;

        try {
            $registry->resolve('unknown');
            $this->fail('Expected ResearchGenerationProviderException to be thrown.');
        } catch (ResearchGenerationProviderException $e) {
            $this->assertSame(ResearchGenerationProviderException::UNKNOWN_PROVIDER, $e->getCode());
        }
    }

    public function test_misconfigured_provider_throws_controlled_exception(): void
    {
        $registry = new ResearchGenerationProviderRegistry(['broken' => \stdClass::class]);

        try {
            $registry->resolve('broken');
            $this->fail('Expected ResearchGenerationProviderException to be thrown.');
        } catch (ResearchGenerationProviderException $e) {
            $this->assertSame(ResearchGenerationProviderException::INVALID_RESPONSE, $e->getCode());
        }
    }
}
