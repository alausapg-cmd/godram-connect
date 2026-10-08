<?php

return [
    // Prefixes for generated identifiers. Changeable without code edits.
    'member_prefix' => env('GODRAM_MEMBER_PREFIX', 'GDM-'),

    'links' => [
        'youtube' => env('GODRAM_YOUTUBE_URL', 'https://www.youtube.com/@GODRAMTV'),
        'facebook' => env('GODRAM_FACEBOOK_URL', 'https://www.facebook.com/share/1HdrHtq6gz/'),
        'whatsapp' => env('GODRAM_WHATSAPP_URL'),
    ],

    'uploads' => [
        'image_max_kb' => 8192,
        'document_max_kb' => 10240,
        'max_files_per_report' => 12,
    ],

    // When true, a banner tells visitors the data is sample data.
    'demo_mode' => env('GODRAM_DEMO_MODE', false),
];
