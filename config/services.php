<?php

return [

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

    'rajaongkir' => [
        'key' => env('RAJAONGKIR_API_KEY'),
        'base_url' => env('RAJAONGKIR_BASE_URL', 'https://pro.rajaongkir.com/api'),
        'mock' => env('RAJAONGKIR_MOCK', false),
    ],

    'brevo' => [
        'key' => env('BREVO_API_KEY'),
        'base_url' => env('BREVO_BASE_URL', 'https://api.brevo.com/v3'),
        'sender_name' => env('BREVO_SENDER_NAME', 'CV. Jajar Wayang'),
        'sender_email' => env('BREVO_SENDER_EMAIL', 'noreply@jajarwayang.com'),
        'admin_email' => env('BREVO_ADMIN_EMAIL', 'gudang@jajarwayang.com'),
    ],

    'midtrans' => [
        'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
        'merchant_id' => env('MIDTRANS_MERCHANT_ID'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'sanitize' => env('MIDTRANS_SANITIZE', true),
        'three_ds' => env('MIDTRANS_3DS', true),

        'snap_sandbox_url' => env('MIDTRANS_SNAP_SANDBOX_URL', 'https://app.sandbox.midtrans.com/snap/v1/transactions'),
        'snap_production_url' => env('MIDTRANS_SNAP_PRODUCTION_URL', 'https://app.midtrans.com/snap/v1/transactions'),

        'api_sandbox_url' => env('MIDTRANS_API_SANDBOX_URL', 'https://api.sandbox.midtrans.com'),
        'api_production_url' => env('MIDTRANS_API_PRODUCTION_URL', 'https://api.midtrans.com'),

        'snap_js_sandbox' => 'https://app.sandbox.midtrans.com/snap/snap.js',
        'snap_js_production' => 'https://app.midtrans.com/snap/snap.js',
    ],

];
