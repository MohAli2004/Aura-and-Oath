<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Brand
    |--------------------------------------------------------------------------
    */
    'name' => env('AURA_BRAND_NAME', 'Aura & Oath'),
    'tagline' => env('AURA_TAGLINE', 'Premium beauty, quietly confident.'),
    'currency' => env('AURA_CURRENCY', 'USD'),
    'currency_symbol' => env('AURA_CURRENCY_SYMBOL', '$'),
    'locale' => env('AURA_LOCALE', 'en_US'),
    'country' => env('AURA_COUNTRY', 'LB'),
    'country_name' => env('AURA_COUNTRY_NAME', 'Lebanon'),

    /*
    |--------------------------------------------------------------------------
    | Contact
    |--------------------------------------------------------------------------
    */
    'contact' => [
        'email' => env('AURA_CONTACT_EMAIL', 'auraandouth@gmail.com'),
        'phone' => env('AURA_CONTACT_PHONE', '+961 81 031 612'),
        'whatsapp' => env('AURA_WHATSAPP', '+96181031612'),
        'address' => env('AURA_ADDRESS', 'Beirut, Lebanon'),
        'support_hours' => env('AURA_SUPPORT_HOURS', 'Mon–Fri, 10:00–18:00'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    */
    'payments' => [
        'wish' => [
            'account_name' => env('AURA_WISH_ACCOUNT_NAME', 'Aura & Oath'),
            'account_number' => env('AURA_WISH_ACCOUNT_NUMBER', '81031612'),
            'instructions' => env(
                'AURA_WISH_INSTRUCTIONS',
                'Send the order total to our Wish account, then include your order number in the transfer note. We will confirm payment before preparing your order.'
            ),
        ],
        'whish' => [
            'enabled' => (bool) env('WHISH_ENABLED', false),
            'channel' => env('WHISH_CHANNEL', ''),
            'secret' => env('WHISH_SECRET', ''),
            'website_url' => env('WHISH_WEBSITE_URL', env('APP_URL', 'http://localhost')),
            'environment' => env('WHISH_ENVIRONMENT', 'sandbox'),
        ],
        'whatsapp' => [
            'admin_phone' => env('WHATSAPP_ADMIN_PHONE', ''),
            'access_token' => env('WHATSAPP_ACCESS_TOKEN', ''),
            'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID', ''),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Social
    |--------------------------------------------------------------------------
    */
    'social' => [
        'instagram' => env('AURA_INSTAGRAM', 'https://instagram.com/auraandoath'),
        'facebook' => env('AURA_FACEBOOK', 'https://facebook.com/auraandoath'),
        'tiktok' => env('AURA_TIKTOK', 'https://tiktok.com/@auraandoath'),
        'youtube' => env('AURA_YOUTUBE', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Colors (CSS reference)
    |--------------------------------------------------------------------------
    */
    'colors' => [
        'ivory' => '#F7F3EE',
        'beige' => '#E8DFD4',
        'blush' => '#D4A5A5',
        'taupe' => '#8B7E74',
        'charcoal' => '#2C2A28',
        'gold' => '#C4A574',
    ],

    /*
    |--------------------------------------------------------------------------
    | Inventory Strategy
    |--------------------------------------------------------------------------
    |
    | On order place: reserve stock (increment reserved_quantity).
    | On approve: convert reserved → sold (deduct stock_quantity, clear reserved).
    | On reject/cancel: release reserved (decrement reserved_quantity).
    | On return (admin confirms resellable): restore stock (increment stock_quantity).
    |
    | available = stock_quantity - reserved_quantity
    | Never allow negative stock or reserved quantities.
    |
    */
    'inventory' => [
        'strategy' => 'reserve_on_place_convert_on_approve',
        'default_low_stock_threshold' => 5,
        'allow_oversell' => false,
        'notes' => [
            'place' => 'Reserve stock when order is placed (PendingApproval).',
            'approve' => 'Convert reserved quantity to sold; deduct from stock_quantity.',
            'reject_or_cancel' => 'Release reserved quantity back to available.',
            'return_resellable' => 'Restore stock_quantity when admin confirms resellable return.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Orders
    |--------------------------------------------------------------------------
    */
    'orders' => [
        'number_prefix' => 'AO',
        'idempotency_ttl_minutes' => 60,
        'guest_checkout' => false,
        'return_window_hours' => 24,
    ],

    /*
    |--------------------------------------------------------------------------
    | Print documents (invoice / packing slip)
    |--------------------------------------------------------------------------
    |
    | Field keys are stored as invoice_fields / packing_slip_fields (JSON).
    | Page sizes are stored as invoice_size / packing_slip_size (A4|A5).
    |
    */
    'print' => [
        'sizes' => [
            'A4' => [
                'width' => '210mm',
                'height' => '297mm',
                'margin' => '14mm',
            ],
            'A5' => [
                'width' => '148mm',
                'height' => '210mm',
                'margin' => '10mm',
            ],
        ],
        'defaults' => [
            'invoice' => 'A5',
            'packing_slip' => 'A4',
        ],
        'invoice' => [
            'brand' => 'Brand name',
            'customer_name' => 'Customer name',
            'customer_email' => 'Customer email',
            'customer_phone' => 'Customer phone',
            'order_date' => 'Order date & time',
            'ship_to' => 'Ship-to address',
            'item' => 'Item name',
            'sku' => 'SKU',
            'quantity' => 'Quantity',
            'unit_price' => 'Unit price',
            'line_total' => 'Line total',
            'subtotal' => 'Subtotal',
            'discount' => 'Discount',
            'delivery' => 'Delivery fee',
            'tax' => 'Tax',
            'total' => 'Grand total',
        ],
        'packing_slip' => [
            'order_date' => 'Order date & time',
            'customer_name' => 'Customer name',
            'ship_to' => 'Ship-to address',
            'phone' => 'Customer phone',
            'tracking' => 'Tracking number',
            'sku' => 'SKU',
            'barcode' => 'Barcode',
            'item' => 'Item name',
            'quantity' => 'Quantity',
            'picked' => 'Picked checkbox',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin seed (change in production)
    |--------------------------------------------------------------------------
    */
    'admin' => [
        'email' => env('AURA_ADMIN_EMAIL', 'admin@auraandoath.com'),
        'password' => env('AURA_ADMIN_PASSWORD', 'password'),
        'name' => env('AURA_ADMIN_NAME', 'Aura Admin'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination & shop defaults
    |--------------------------------------------------------------------------
    */
    'shop' => [
        'per_page' => 12,
        'free_shipping_threshold' => null,
    ],

];
