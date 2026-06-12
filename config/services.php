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

    'plausible' => [
        'domain' => env('PLAUSIBLE_DOMAIN'),
        'script_url' => env('PLAUSIBLE_SCRIPT_URL', 'https://plausible.io/js/script.js'),
    ],

    'web_push' => [
        'enabled' => env('WEB_PUSH_ENABLED', false),
        'vapid_subject' => env('WEB_PUSH_VAPID_SUBJECT', env('APP_URL', 'http://localhost')),
        'vapid_public_key' => env('WEB_PUSH_VAPID_PUBLIC_KEY'),
        'vapid_private_key' => env('WEB_PUSH_VAPID_PRIVATE_KEY'),
        'min_severity' => env('WEB_PUSH_MIN_SEVERITY', 'warning'),
        'notify_recoveries' => env('WEB_PUSH_NOTIFY_RECOVERIES', true),
    ],

];
