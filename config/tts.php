<?php

use App\Services\TTS\Providers\FakeTtsProvider;

return [
    'default_provider' => 'fake',

    'providers' => [
        'fake' => FakeTtsProvider::class,
    ],
];
