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

    // ERP HPY — system of record for all app data.
    // When enabled + configured, repositories pull from ERP HPY instead of local SQLite.
    // Auth is session/login based: POST /api/method/login with usr + pwd -> sid cookie.
    'erpnext' => [
        'enabled' => env('ERPNEXT_ENABLED', false),
        'url' => env('ERPNEXT_URL'),                    // e.g. https://kbm.hpy.co.id
        'username' => env('ERPNEXT_USERNAME'),          // ERP HPY login (email / user id)
        'password' => env('ERPNEXT_PASSWORD'),

        // Preferred service credentials: an API key pair beats username/password
        // because a token never expires and needs no cookie to survive.
        'api_key' => env('ERPNEXT_API_KEY'),
        'api_secret' => env('ERPNEXT_API_SECRET'),

        'timeout' => env('ERPNEXT_TIMEOUT', 15),
        'verify' => env('ERPNEXT_VERIFY_SSL', true),    // set false only on machines whose
                                                        // antivirus MITMs TLS (e.g. Avast)

        // Crew Master is read from and written to this Doctype (confirmed: Employee).
        'crew_doctype' => env('ERPNEXT_CREW_DOCTYPE', 'Employee'),

        // Employee.company is mandatory; leave blank to let ERP HPY apply its default.
        'company' => env('ERPNEXT_COMPANY'),
    ],

];
