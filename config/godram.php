<?php

return [
    // Prefixes for generated identifiers. Changeable without code edits.
    'member_prefix' => env('GODRAM_MEMBER_PREFIX', 'GDM-'),

    'links' => [
        'youtube' => env('GODRAM_YOUTUBE_URL', 'https://www.youtube.com/@GODRAMTV'),
        'facebook' => env('GODRAM_FACEBOOK_URL', 'https://www.facebook.com/share/1HdrHtq6gz/'),
        'whatsapp' => env('GODRAM_WHATSAPP_URL'),
    ],

    // GODRAM TV. New uploads are imported hourly (godram:sync-youtube) into the Watch centre.
    'youtube' => [
        'channel_id' => env('GODRAM_YOUTUBE_CHANNEL_ID', 'UCRJnL2sj9MASfswKg9Sle1A'),
        'import_category' => env('GODRAM_YOUTUBE_IMPORT_CATEGORY', 'drama_performances'),
    ],

    'uploads' => [
        'image_max_kb' => 8192,
        'document_max_kb' => 10240,
        'max_files_per_report' => 12,
        'resource_max_kb' => 25600,
    ],

    // When true, a banner tells visitors the data is sample data.
    'demo_mode' => env('GODRAM_DEMO_MODE', false),

    // "Did you know?" facts on the home page, one a day. Taken from the GODRAM history.
    'did_you_know' => [
        'GODRAM began in 1991, when Paul Adaramola started training drama groups in Lagos with the permission of the Lagos District Overseer, Pastor S. A. Abiodun.',
        'The first class of 41 drama ministers graduated from the GODRAM Institute of Christian Drama in 1995.',
        'GOFAMINT recognised GODRAM as a national department in 1996, after the films "Your Choice" and "Ohun Too Yan".',
        'GODRAM productions have been staged at the National Theatre, Iganmu, and at cultural centres in Ibadan, Benin and Port Harcourt.',
        '"Valley of Baca" (Afonifoji Omije) is one of more than a dozen films made by GODRAM.',
        'In 1999 the GODRAM Institute of Christian Drama offered both Ordinary and Advanced Certificates in Christian Drama.',
        'GODRAM\'s 1994 Victory Drama Crusade at Bolorunpelu, Egbe, presented "Majemu (Covenant)" over three days.',
    ],
];
