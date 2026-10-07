<?php

namespace Tests\Unit;

use App\Services\TTS\NarrationHasher;
use PHPUnit\Framework\TestCase;

class NarrationHasherTest extends TestCase
{
    public function test_same_input_produces_the_same_hash(): void
    {
        $text = 'Kucing suka kardus karena terasa aman.';

        $this->assertSame(NarrationHasher::hash($text), NarrationHasher::hash($text));
    }

    public function test_different_input_produces_a_different_hash(): void
    {
        $this->assertNotSame(NarrationHasher::hash('Kucing suka.'), NarrationHasher::hash('Kucing tidur.'));
    }

    public function test_hash_is_a_64_character_hexadecimal_string(): void
    {
        $this->assertSame(64, strlen(NarrationHasher::hash('Kucing suka kardus.')));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', NarrationHasher::hash('Kucing suka kardus.'));
    }

    public function test_whitespace_and_case_are_preserved_exactly(): void
    {
        $this->assertNotSame(NarrationHasher::hash('Kucing suka kardus.'), NarrationHasher::hash('kucing suka kardus.'));
        $this->assertNotSame(NarrationHasher::hash('Kucing suka kardus.'), NarrationHasher::hash(' Kucing suka kardus.'));
    }

    public function test_matches_a_known_sha256_vector(): void
    {
        $this->assertSame(
            'ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad',
            NarrationHasher::hash('abc')
        );
    }
}
