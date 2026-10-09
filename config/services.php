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

    // Subscription payments (finding C1). Plan prices are charged in this
    // currency; Paystack takes amounts in the smallest unit (kobo).
    'paystack' => [
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
        'currency' => env('PAYSTACK_CURRENCY', 'NGN'),
    ],

    // SMS and WhatsApp reminders (session 16). MyBooks holds the account.
    // Termii docs (checked 2026-10-06): https://developers.termii.com/messaging-api
    // The base URL is shown on each account's Termii dashboard.
    'termii' => [
        'api_key' => env('TERMII_API_KEY'),
        'base_url' => env('TERMII_BASE_URL', 'https://v3.api.termii.com'),
        // Registered sender ID, 3-11 letters/digits, approved by Termii.
        'sender_id' => env('TERMII_SENDER_ID', 'MyBooks'),
        // 'dnd' delivers transactional messages to numbers on the NCC
        // Do-Not-Disturb list too; 'generic' does not (and MTN holds generic
        // messages between 8pm and 8am).
        'sms_channel' => env('TERMII_SMS_CHANNEL', 'dnd'),
        // Signs webhook events (X-Termii-Signature, HMAC-SHA512 of the body).
        'secret_key' => env('TERMII_SECRET_KEY'),
        // WhatsApp through Termii: the device (WhatsApp number) set up on Termii.
        'whatsapp_device_id' => env('TERMII_WHATSAPP_DEVICE_ID'),
    ],

    // WhatsApp Cloud API (Meta), the other WhatsApp option (checked 2026-10-06):
    // https://developers.facebook.com/docs/whatsapp/cloud-api/guides/send-message-templates
    'whatsapp_meta' => [
        'token' => env('WHATSAPP_META_TOKEN'),
        'phone_number_id' => env('WHATSAPP_META_PHONE_NUMBER_ID'),
        'api_version' => env('WHATSAPP_META_API_VERSION', 'v25.0'),
        'base_url' => env('WHATSAPP_META_BASE_URL', 'https://graph.facebook.com'),
        // Signs webhook events (X-Hub-Signature-256).
        'app_secret' => env('WHATSAPP_META_APP_SECRET'),
        // Answer to Meta's webhook check (hub.verify_token).
        'verify_token' => env('WHATSAPP_META_VERIFY_TOKEN'),
        'language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'en'),
    ],

    // Bank feeds (session 17). MyBooks holds one Mono account for all businesses.
    // Mono docs (checked 2026-10-09): https://docs.mono.co/docs/financial-data/connect-link
    // The secret key goes in the mono-sec-key header; test keys use the sandbox.
    'mono' => [
        'secret_key' => env('MONO_SECRET_KEY'),
        // Only needed if the Connect widget is used instead of the hosted link.
        'public_key' => env('MONO_PUBLIC_KEY'),
        // Sent back by Mono in the mono-webhook-secret header on every event.
        'webhook_secret' => env('MONO_WEBHOOK_SECRET'),
        'base_url' => env('MONO_BASE_URL', 'https://api.withmono.com'),
    ],

];
