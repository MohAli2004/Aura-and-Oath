<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Response security headers
    |--------------------------------------------------------------------------
    |
    | Applied by App\Http\Middleware\SecurityHeaders. CSP can be put in
    | report-only mode first if you want to watch for breakage before
    | enforcing it.
    |
    */

    'headers' => [
        'enabled' => env('SECURITY_HEADERS_ENABLED', true),
        'csp_enabled' => env('SECURITY_CSP_ENABLED', true),
        'csp_report_only' => env('SECURITY_CSP_REPORT_ONLY', false),

        // Extra origins your pages are allowed to talk to, comma separated.
        'extra_script_src' => env('SECURITY_EXTRA_SCRIPT_SRC', ''),
        'extra_connect_src' => env('SECURITY_EXTRA_CONNECT_SRC', ''),
        'extra_frame_src' => env('SECURITY_EXTRA_FRAME_SRC', ''),

        // Seconds. Only sent over HTTPS.
        'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
        'hsts_preload' => env('SECURITY_HSTS_PRELOAD', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTPS
    |--------------------------------------------------------------------------
    |
    | Force generated URLs (and redirects) onto https. Leave null to decide
    | automatically: on when APP_URL starts with https://.
    |
    */

    'force_https' => env('FORCE_HTTPS'),

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    |
    | Uploaded files are renamed and the extension is derived from the real
    | detected image type, never from the client filename. SVG is excluded on
    | purpose: it is an active document and can carry script.
    |
    */

    'uploads' => [
        'allowed_mimes' => [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/avif' => 'avif',
            'image/vnd.microsoft.icon' => 'ico',
            'image/x-icon' => 'ico',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Web push
    |--------------------------------------------------------------------------
    |
    | Browsers hand us a push endpoint that the server later posts to. Only
    | real push services are accepted so the endpoint cannot be pointed at an
    | internal address (SSRF).
    |
    */

    'push_endpoint_hosts' => [
        'fcm.googleapis.com',
        'updates.push.services.mozilla.com',
        'push.services.mozilla.com',
        'web.push.apple.com',
        'notify.windows.com',
        'wns2-*.notify.windows.com',
    ],

];
