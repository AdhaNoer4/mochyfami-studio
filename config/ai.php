<?php

use App\Services\AI\FakeScriptGenerationProvider;

return [
    'default_script_generation_provider' => 'fake',
    'providers' => [
        'fake' => FakeScriptGenerationProvider::class,
    ],
];
