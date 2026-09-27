<?php

use App\Services\AI\FakeResearchGenerationProvider;
use App\Services\AI\FakeScriptGenerationProvider;

return [
    'default_script_generation_provider' => 'fake',
    'providers' => [
        'fake' => FakeScriptGenerationProvider::class,
    ],
    'research' => [
        'default_provider' => 'fake',
        'providers' => [
            'fake' => FakeResearchGenerationProvider::class,
        ],
    ],
];
