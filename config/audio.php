<?php

return [
    'default_provider' => env('AUDIO_PROVIDER', 'magic_chords'),

    'credit_cost' => [
        'analyze' => (int) env('AUDIO_CREDIT_COST_ANALYZE', 1),
        'transcribe' => (int) env('AUDIO_CREDIT_COST_TRANSCRIBE', 1),
        'create' => (int) env('AUDIO_CREDIT_COST_CREATE', 2),
        'stems' => (int) env('AUDIO_CREDIT_COST_STEMS', 2),
    ],

    'providers' => [
        'magic_chords' => [
            'base_url' => env('MAGIC_CHORDS_BASE_URL', 'https://magic-chords.dev/api/v1'),
            'timeout' => (int) env('MAGIC_CHORDS_TIMEOUT', 60),
        ],
        'elevenlabs' => [
            'api_key' => env('ELEVENLABS_API_KEY'),
            'base_url' => env('ELEVENLABS_BASE_URL', 'https://api.elevenlabs.io'),
            'timeout' => (int) env('ELEVENLABS_TIMEOUT', 300),
            'model_id' => env('ELEVENLABS_MUSIC_MODEL', 'music_v1'),
            'output_format' => env('ELEVENLABS_MUSIC_OUTPUT_FORMAT', 'mp3_44100_128'),
            'stems_output_format' => env('ELEVENLABS_STEMS_OUTPUT_FORMAT', 'mp3_44100_128'),
            'stem_variation_id' => env('ELEVENLABS_STEM_VARIATION', 'six_stems_v1'),
            // true = generate inside the HTTP request (no queue worker needed)
            'process_sync' => filter_var(env('ELEVENLABS_PROCESS_SYNC', true), FILTER_VALIDATE_BOOLEAN),
            'signed_url_days' => (int) env('ELEVENLABS_SIGNED_URL_DAYS', 7),
            // Defer stems until after the HTTP response (avoids 60s PHP timeout on POST).
            'stems_defer' => filter_var(env('ELEVENLABS_STEMS_DEFER', true), FILTER_VALIDATE_BOOLEAN),
        ],
    ],

    'stems' => [
        'allowed' => ['vocals', 'instrumental', 'drums', 'bass', 'guitar', 'other', 'piano'],
        'aliases' => [
            'vocal' => 'vocals',
            'voice' => 'vocals',
            'singing' => 'vocals',
            'drum' => 'drums',
            'percussion' => 'drums',
            'accompaniment' => 'instrumental',
            'no_vocals' => 'instrumental',
            'inst' => 'instrumental',
            'misc' => 'other',
            'rest' => 'other',
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
