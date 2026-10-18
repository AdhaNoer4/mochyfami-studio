
<?php

use App\Services\TTS\Providers\AudexumTtsProvider;
use App\Services\TTS\Providers\FakeTtsProvider;

return [
    'default_provider' => env('TTS_DEFAULT_PROVIDER', 'fake'),

    'providers' => [
        'fake' => FakeTtsProvider::class,
        'audexum' => AudexumTtsProvider::class,
    ],

    'audexum' => [
        'api_key' => env('AUDEXUM_API_KEY'),
        'endpoint' => env(
            'AUDEXUM_API_URL',
            'https://audexum.com/api/synthesize'
        ),
        'language' => env('AUDEXUM_LANGUAGE', 'id'),
    ],
];
