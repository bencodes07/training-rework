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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'vatsim' => [
        'client_id' => env('VATSIM_CLIENT_ID'),
        'client_secret' => env('VATSIM_CLIENT_SECRET'),
        'redirect_uri' => env('VATSIM_REDIRECT_URI', env('APP_URL') . '/auth/vatsim/callback'),
        'auth_url' => env('VATSIM_AUTH_URL', 'https://auth-dev.vatsim.net/oauth/authorize'),
        'token_url' => env('VATSIM_TOKEN_URL', 'https://auth-dev.vatsim.net/oauth/token'),
        'api_base_url' => env('VATSIM_API_BASE_URL', 'https://auth-dev.vatsim.net/api'),
    ],

    'vateud' => [
        'token' => env('VATEUD_TOKEN'),
        'use_mock' => env('VATEUD_USE_MOCK', false),
        'min_activity_minutes' => (int) env('VATEUD_MIN_ACTIVITY_MINUTES', 180),
        'removal_warning_days' => (int) env('VATEUD_REMOVAL_WARNING_DAYS', 31),
        'min_endorsement_age_days' => (int) env('VATEUD_MIN_ENDORSEMENT_AGE_DAYS', 180),
    ],

    'training' => [
        'min_hours' => (int) env('TRAINING_MIN_HOURS', 25),
        'min_activity' => (int) env('TRAINING_MIN_ACTIVITY', 10),
        'display_activity' => (int) env('TRAINING_DISPLAY_ACTIVITY', 8),
        's3_rating_change_days' => (int) env('TRAINING_S3_RATING_CHANGE_DAYS', 90),
    ],

    'vatger' => [
        'api_key' => env('VATGER_API_KEY'),
        'api_url' => env('VATGER_API_URL', 'http://vatsim-germany.org/api'),
    ],
];
