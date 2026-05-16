<?php

$enabledShops = array_values(array_filter(array_map(
    fn ($shop) => strtolower(trim($shop)),
    explode(',', (string) env('SHOP_ENABLED_SHOPS', 'supermarket'))
)));

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    'product_units' => [ 
        "kg",
        "g",
        "mg",
        
        // Volume
        "litre",
        "ml",
        
        // Count
        "piece",
        "dozen",
        "pair",
        
        // Packaged / measured
        "packet",
        "bottle",
        "box",
        "bundle",
        "roll",
        "can",
        "tube",
        "sachet",
        "strip",
        "sheet",
        "metre",
        "cm"
    ],

    'purchase_units' => [
        "bag",
        "sack",
        "gunny bag",
        
        // Boxes / Cartons
        "box",
        "carton",
        "crate",
        "case",
        "tray",
        
        // Tins / Containers
        "tin",
        "drum",
        "barrel",
        "canister",
        "jar",
        
        // Bundles / Rolls
        "bundle",
        "bale",
        "roll",
        "reel",
        
        // Count based
        "dozen",
        "gross",
        "pack"
    ],

    'payment_methods' => [
        'Cash', 'UPI', 'Card'
    ],

    'default_shop' => env('SHOP_DEFAULT', 'supermarket'),

    'enabled_shops' => ['supermarket'], // add shops here ['egg', 'supermarket']

    'shops' => [
        'supermarket' => [
            'label' => 'Supermarket',
            'default_page' => 'billing',
            'pages' => [
                [
                    'key' => 'billing',
                    'label' => 'Billing',
                    'permission' => 'supermarket.billing',
                    'title' => 'Billing Terminal',
                    'subtitle' => 'Create and manage customer bills',
                ],
                [
                    'key' => 'products',
                    'label' => 'Products',
                    'permission' => 'supermarket.products',
                    'title' => 'Product Inventory',
                    'subtitle' => 'Manage, update and track products',
                ],
                [
                    'key' => 'stock',
                    'label' => 'Stock',
                    'permission' => 'supermarket.stock',
                    'title' => 'Stock Intake',
                    'subtitle' => 'Track, add, edit and reverse supplier purchases',
                ],
                [
                    'key' => 'billdetails',
                    'label' => 'Bill Details',
                    'permission' => 'supermarket.billdetails',
                    'title' => 'Bill Details',
                    'subtitle' => 'View and search past transactions',
                ],
                [
                    'key' => 'reports',
                    'label' => 'Reports',
                    'permission' => 'supermarket.reports',
                    'title' => 'Reports',
                    'subtitle' => 'Sales, profit and product analytics',
                ],
            ],
        ],
        'egg' => [
            'label' => 'Egg Tracking',
            'default_page' => 'entry',
            'pages' => [
                [
                    'key' => 'entry',
                    'label' => 'Egg Entry',
                    'permission' => 'egg.entry',
                    'title' => 'Daily Egg Entry',
                    'subtitle' => 'Record daily egg stock and sales',
                ],
                [
                    'key' => 'egreports',
                    'label' => 'Reports',
                    'permission' => 'egg.reports',
                    'title' => 'Egg Reports',
                    'subtitle' => 'Egg tracking analytics and performance',
                ],
            ],
        ],
    ],

    'role_permissions' => [
        'admin' => ['*'],
        'user' => [
            'supermarket.billing',
            'supermarket.products',
            'supermarket.stock',
            'supermarket.billdetails',
            'egg.entry',
        ],
    ],

    'shop_info' => [
        'company_name' => 'KSR',
        'name' => "NEAL DR MINI MART",
        'address' => "Trivandram main Rd, Chunkankadai, 629003",
        'phone' => "+91 93446 84637"
    ]

];
