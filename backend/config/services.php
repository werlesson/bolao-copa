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

    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI'),
    ],

    'football_data' => [
        'api_key'       => env('FOOTBALL_DATA_API_KEY'),
        // PL = Premier League (testes). WC = Copa do Mundo.
        'competition'   => env('FOOTBALL_DATA_COMPETITION', 'PL'),
        'days_past'     => (int) env('FOOTBALL_DATA_DAYS_PAST', 7),
        'days_ahead'    => (int) env('FOOTBALL_DATA_DAYS_AHEAD', 21),
    ],

    'vapid' => [
        'subject'     => env('VAPID_SUBJECT'),
        'public_key'  => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],

    'gemini' => [
        'api_key'           => env('GEMINI_API_KEY'),
        'model'             => env('GEMINI_MODEL', 'gemini-2.5-flash-lite'),
        'max_output_tokens' => (int) env('GEMINI_MAX_OUTPUT_TOKENS', 64),
        'temperature'       => (float) env('GEMINI_TEMPERATURE', 0.55),
        'prompt_version'    => env('BULLETIN_PROMPT_VERSION', '3'),
    ],

    'ai_ranking' => [
        'enabled'      => filter_var(env('AI_RANKING_ENABLED', false), FILTER_VALIDATE_BOOL),
        'daily_budget' => (int) env('AI_RANKING_DAILY_BUDGET', 0),
    ],

];
