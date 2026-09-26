<?php

use App\Services\Research\FakeSearchProvider;

return [
    'search' => [
        'default_provider' => 'fake',
        'providers' => [
            'fake' => FakeSearchProvider::class,
        ],
    ],
];
