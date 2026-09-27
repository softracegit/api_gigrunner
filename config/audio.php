<?php

return [
    'default_provider' => env('AUDIO_PROVIDER', 'magic_chords'),

    'credit_cost' => [
        'analyze' => (int) env('AUDIO_CREDIT_COST_ANALYZE', 1),
        'transcribe' => (int) env('AUDIO_CREDIT_COST_TRANSCRIBE', 1),
    ],

    'providers' => [
        'magic_chords' => [
            'base_url' => env('MAGIC_CHORDS_BASE_URL', 'https://magic-chords.dev/api/v1'),
            'timeout' => (int) env('MAGIC_CHORDS_TIMEOUT', 60),
        ],
    ],

    'upload' => [
        'disk' => env('AUDIO_UPLOAD_DISK', 'local'),
        'max_kb' => (int) env('AUDIO_UPLOAD_MAX_KB', 51200), // 50 MB
        'mimetypes' => [
            'audio/mpeg',
            'audio/mp3',
            'audio/wav',
            'audio/x-wav',
            'audio/flac',
            'audio/ogg',
            'audio/mp4',
            'audio/x-m4a',
            'audio/aac',
            'video/mp4',
            'application/octet-stream',
        ],
    ],
];
