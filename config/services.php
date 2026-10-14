<?php

return [

    'facebook' => [
        'authorization_url' => env('FACEBOOK_AUTHORIZATION_URL', 'https://www.facebook.com/v26.0/dialog/oauth'),
        'graph_url' => env('FACEBOOK_GRAPH_URL', 'https://graph.facebook.com/v26.0'),
        'page_name' => env('FACEBOOK_PAGE_NAME', 'Digi Emperor-Digital Marketing Agency'),
        'scopes' => ['pages_show_list', 'pages_read_engagement', 'leads_retrieval'],
        'timeout' => env('FACEBOOK_TIMEOUT', 30),
        'max_pages' => env('FACEBOOK_MAX_PAGES', 100),
    ],

    'website_leads' => [
        'url' => env('WEBSITE_LEADS_URL', 'https://digiemperor.com/wp-json/custom-api/v1/leads'),
        'per_page' => env('WEBSITE_LEADS_PER_PAGE', 50),
        'max_pages' => env('WEBSITE_LEADS_MAX_PAGES', 100),
        'timeout' => env('WEBSITE_LEADS_TIMEOUT', 30),
    ],

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

];
