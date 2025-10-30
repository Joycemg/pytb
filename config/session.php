<?php

use Illuminate\Support\Str;

/**
 * config/session.php
 *
 * Ajustado para hosting compartido (Hostinger):
 * - Driver por defecto "database" (persistente y sin daemons raros).
 * - Valores por ENV, con “secure” auto-true si APP_URL es https.
 * - Cookie name con prefijo del APP_NAME.
 * - Lotería de limpieza más liviana para reducir carga en DB.
 */

$appUrl = (string) env('APP_URL', 'http://localhost');
$scheme = strtolower((string) parse_url($appUrl, PHP_URL_SCHEME));
$isHttps = $scheme === 'https';

return [

    /*
    |--------------------------------------------------------------------------
    | Default Session Driver
    |--------------------------------------------------------------------------
    |
    | "database" es una buena opción en hosting compartido (sin procesos
    | residentes). Asegurate de tener la tabla "sessions" migrada.
    |
    | Supported: "file", "cookie", "database", "memcached", "redis", "dynamodb", "array"
    |
    */

    'driver' => env('SESSION_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Session Lifetime
    |--------------------------------------------------------------------------
    */

    'lifetime' => (int) env('SESSION_LIFETIME', 120),
    'expire_on_close' => (bool) env('SESSION_EXPIRE_ON_CLOSE', false),

    /*
    |--------------------------------------------------------------------------
    | Session Encryption
    |--------------------------------------------------------------------------
    |
    | Activá por ENV si querés cifrar el payload de la sesión.
    |
    */

    'encrypt' => (bool) env('SESSION_ENCRYPT', false),

    /*
    |--------------------------------------------------------------------------
    | Session File Location (si usás "file")
    |--------------------------------------------------------------------------
    */

    'files' => storage_path('framework/sessions'),

    /*
    |--------------------------------------------------------------------------
    | Session Database Connection / Table (si usás "database")
    |--------------------------------------------------------------------------
    */

    'connection' => env('SESSION_CONNECTION'),
    'table' => env('SESSION_TABLE', 'sessions'),

    /*
    |--------------------------------------------------------------------------
    | Session Cache Store (para backends cacheados)
    |--------------------------------------------------------------------------
    |
    | Afecta: "dynamodb", "memcached", "redis"
    |
    */

    'store' => env('SESSION_STORE'),

    /*
    |--------------------------------------------------------------------------
    | Session Sweeping Lottery
    |--------------------------------------------------------------------------
    |
    | Barrido de sesiones expiradas. Más espaciado para bajar carga en DB
    | en hostings compartidos (1/200 en vez de 2/100).
    |
    */

    'lottery' => [1, 200],

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Name
    |--------------------------------------------------------------------------
    */

    'cookie' => env(
        'SESSION_COOKIE',
        Str::slug((string) env('APP_NAME', 'laravel')) . '-session'
    ),

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Path / Domain
    |--------------------------------------------------------------------------
    */

    'path' => env('SESSION_PATH', '/'),
    'domain' => env('SESSION_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | HTTPS Only Cookies
    |--------------------------------------------------------------------------
    |
    | Por defecto, true cuando APP_URL es https; se puede forzar por ENV.
    |
    */

    'secure' => env('SESSION_SECURE_COOKIE', $isHttps),

    /*
    |--------------------------------------------------------------------------
    | HTTP Only
    |--------------------------------------------------------------------------
    */

    'http_only' => (bool) env('SESSION_HTTP_ONLY', true),

    /*
    |--------------------------------------------------------------------------
    | Same-Site Cookies
    |--------------------------------------------------------------------------
    |
    | "lax" es seguro por defecto. Si usás "none", recordá exigir HTTPS.
    |
    | Supported: "lax", "strict", "none", null
    |
    */

    'same_site' => env('SESSION_SAME_SITE', 'lax'),

    /*
    |--------------------------------------------------------------------------
    | Partitioned Cookies
    |--------------------------------------------------------------------------
    |
    | Solo tiene efecto con secure=true y same_site="none".
    |
    */

    'partitioned' => (bool) env('SESSION_PARTITIONED_COOKIE', false),

];
