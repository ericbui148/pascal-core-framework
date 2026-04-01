<?php

// config/auth.php — add the 'pascal' guard
// Merge this into your existing config/auth.php

return [

    'defaults' => [
        'guard'     => 'web',
        'passwords' => 'users',
    ],

    'guards' => [
        'web' => [
            'driver'   => 'session',
            'provider' => 'users',
        ],

        // Pascal Platform guard — uses pascal_users table via PascalUser model
        'pascal' => [
            'driver'   => 'session',
            'provider' => 'pascal_users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model'  => App\Models\User::class,
        ],

        // Provider for pascal_users table
        'pascal_users' => [
            'driver' => 'eloquent',
            'model'  => App\Models\PascalUser::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table'    => 'password_reset_tokens',
            'expire'   => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,
];
