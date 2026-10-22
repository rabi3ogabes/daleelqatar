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

    // Google Places API (New): matching listings to Google Maps and showing Google reviews.
    // Use a server key restricted to the Places API (New).
    'google_places' => [
        'key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    // Claude writes Arabic name, description and services suggestions for admin review.
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5-5'),
        // Quick, inexpensive model for interactive features (smart search).
        'fast_model' => env('ANTHROPIC_FAST_MODEL', 'claude-haiku-5-5'),
        'base_url' => env('ANTHROPIC_BASE_URL'),
    ],

    // Google Analytics / AdSense / verification tags (IDs are managed in Admin → Settings).
    'integrations' => [
        'enabled' => env('INTEGRATIONS_ENABLED', env('APP_ENV', 'production') === 'production'),
    ],

];
