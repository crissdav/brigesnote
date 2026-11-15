<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // API pública de MINEDU / ESCALE usada por el comando sync:locations.
    'escale' => [
        'base_url' => env('ESCALE_BASE_URL', 'https://escale.minedu.gob.pe/padron/rest'),
        'timeout' => (int) env('ESCALE_TIMEOUT', 30),
        'retries' => (int) env('ESCALE_RETRIES', 5),
        'page_size' => (int) env('ESCALE_PAGE_SIZE', 500),
    ],

];
