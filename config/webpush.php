<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Web Push (browser notifications while away from the site)
    |--------------------------------------------------------------------------
    |
    | Generate keys: php artisan webpush:vapid
    | Then set VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY / VAPID_SUBJECT in .env
    |
    */
    'vapid' => [
        'subject' => env('VAPID_SUBJECT', env('APP_URL', 'mailto:admin@example.com')),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],
];
