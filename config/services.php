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

    // Auth
    'vatsim' => [
        'client_id' => env('VATSIM_CLIENT_ID'),
        'client_secret' => env('VATSIM_CLIENT_SECRET'),
        'redirect_uri' => env('VATSIM_REDIRECT_URI', env('APP_URL') . '/auth/vatsim/callback'),
        'auth_url' => env('VATSIM_AUTH_URL', 'https://auth-dev.vatsim.net/oauth/authorize'),
        'token_url' => env('VATSIM_TOKEN_URL', 'https://auth-dev.vatsim.net/oauth/token'),
        'api_base_url' => env('VATSIM_API_BASE_URL', 'https://auth-dev.vatsim.net/api'),
    ],

    // Endorsement Data
    'vateud' => [
        'token' => env('VATEUD_TOKEN'),
        'use_mock' => env('VATEUD_USE_MOCK', false),
        'min_activity_minutes' => (int) env('VATEUD_MIN_ACTIVITY_MINUTES', 180),
        'removal_warning_days' => (int) env('VATEUD_REMOVAL_WARNING_DAYS', 31),
        'min_endorsement_age_days' => (int) env('VATEUD_MIN_ENDORSEMENT_AGE_DAYS', 180),
    ],

    // Waiting list / Roster data
    'training' => [
        // Minimum activity hours required for different positions
        'min_activity' => env('TRAINING_MIN_ACTIVITY', 10),
        'display_activity' => env('TRAINING_DISPLAY_ACTIVITY', 8),

        // Minimum hours required for rating courses
        'min_hours' => env('TRAINING_MIN_HOURS', 25),

        // S3 rating change restriction (days)
        's3_rating_change_days' => env('S3_RATING_CHANGE_DAYS', 90),

        // Roster activity thresholds
        'roster_inactivity_warning_days' => env('ROSTER_INACTIVITY_WARNING_DAYS', 330), // 11 months
        'roster_removal_grace_days' => env('ROSTER_REMOVAL_GRACE_DAYS', 35),
        'roster_max_inactivity_days' => env('ROSTER_MAX_INACTIVITY_DAYS', 366), // 1 year + 1 day
    ],

    // Notifications
    'vatger' => [
        'api_key' => env('VATGER_API_KEY'),
        'api_url' => env('VATGER_API_URL', 'https://vatsim-germany.org/api'),
    ],
];
