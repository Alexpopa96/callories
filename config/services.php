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

    'anthropic' => [
        // default for users who have not picked a model in their profile
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5'),
        'models' => [
            'claude-haiku-4-5' => 'Claude Haiku 4.5 (cel mai rapid și ieftin)',
            'claude-sonnet-5' => 'Claude Sonnet 5 (echilibrat)',
            'claude-opus-5' => 'Claude Opus 5 (mai precis, mai scump)',
            'claude-fable-5-1' => 'Claude Fable 5.1 (cel mai capabil, cel mai scump)',
        ],
        'daily_scan_limit' => (int) env('SCAN_DAILY_LIMIT', 20),
        'daily_assistant_limit' => (int) env('ASSISTANT_DAILY_LIMIT', 30),
        'daily_text_limit' => (int) env('TEXT_DAILY_LIMIT', 30),
        'daily_workout_limit' => (int) env('WORKOUT_DAILY_LIMIT', 30),
    ],

    'webpush' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', 'mailto:admin@example.com'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
