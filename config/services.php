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

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'plaid' => [
        // Sandbox configuration (default)
        'client_id' => env('PLAID_CLIENT_ID'),
        'secret' => env('PLAID_SECRET'),
        'base_url' => env('PLAID_URL', 'https://sandbox.plaid.com'),
        'environment' => env('PLAID_ENV', 'sandbox'),

        // Production configuration
        'prod_client_id' => env('PLAID_PROD_CLIENT_ID'),
        'prod_secret' => env('PLAID_PROD_SECRET'),
        'prod_base_url' => env('PLAID_PROD_URL', 'https://production.plaid.com'),
        'prod_environment' => env('PLAID_PROD_ENV', 'production'),
    ],

    'stripe' => [
        // Sandbox configuration (default)
        'key' => env('STRIPE_PUBLISHABLE_KEY'),
        'secret' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),

        // Production configuration
        'prod_key' => env('STRIPE_PROD_PUBLISHABLE_KEY'),
        'prod_secret' => env('STRIPE_PROD_SECRET_KEY'),
        'prod_webhook_secret' => env('STRIPE_PROD_WEBHOOK_SECRET'),

        // ACH Payment Method Configuration
        'ach_payment_method' => env('STRIPE_ACH_PAYMENT_METHOD', 'payment_intents'), // 'payment_intents' or 'charges'
        'instant_verification' => env('STRIPE_INSTANT_VERIFICATION', true), // Enable instant bank verification

        // Financial Connections Configuration
        'financial_connections' => [
            'enabled' => env('STRIPE_FINANCIAL_CONNECTIONS_ENABLED', true),
            'verification_priority' => env('STRIPE_VERIFICATION_PRIORITY', 'instant'), // 'instant' or 'microdeposit'
            'require_financial_connections' => env('STRIPE_REQUIRE_FINANCIAL_CONNECTIONS', false), // Force FC usage
            'fallback_to_microdeposits' => env('STRIPE_FALLBACK_TO_MICRODEPOSITS', false), // Disabled - use direct routing/account numbers instead
            'force_instant_verification' => env('STRIPE_FORCE_INSTANT_VERIFICATION', true), // Force instant methods only
        ],
    ],

];
