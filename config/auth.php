<?php
/** config/auth.php */
return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => env('AUTH_PROVIDER', 'users'),
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', App\Models\Usuario::class),
        ],
        // Alternativa database si alguna vez la necesitás:
        // 'users' => ['driver' => 'database', 'table' => env('AUTH_TABLE', 'usuarios')],
    ],

    'passwords' => [
        'users' => [
            'provider' => env('AUTH_PROVIDER', 'users'),
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => (int) env('AUTH_PASSWORD_RESET_EXPIRE', 60),
            'throttle' => (int) env('AUTH_PASSWORD_RESET_THROTTLE', 60),
        ],
    ],

    'password_timeout' => (int) env('AUTH_PASSWORD_TIMEOUT', 10800),

    // Ajustes usados por tu AuthServiceProvider
    'super_ability' => env('AUTH_SUPER_ABILITY', 'superadmin'),
    'admin_roles' => env('AUTH_ADMIN_ROLES', 'admin'),
];
