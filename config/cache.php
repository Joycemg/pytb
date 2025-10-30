<?php

use Illuminate\Support\Str;

/**
 * config/cache.php
 *
 * Ajustado para hosting compartido (Hostinger):
 * - Store por defecto configurable (DATABASE recomendado en compartido).
 * - Tablas/locks con nombres por .env y defaults seguros.
 * - Prefijo de claves para evitar colisiones entre apps en el mismo server.
 * - Drivers “grandes” (Redis/Memcached) quedan opcionales, sin requerirse.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | En Hostinger compartido suele ser más compatible el driver "database"
    | o "file". Podés cambiarlo por .env: CACHE_STORE=database|file|array|...
    |
    */
    'default' => env('CACHE_STORE', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    |
    | Definición de stores. No se requieren extensiones externas: si no tenés
    | Redis/Memcached simplemente dejalos sin usar.
    |
    | Drivers soportados: "array", "database", "file", "memcached",
    |                     "redis", "dynamodb", "octane", "null"
    |
    */
    'stores' => [

        // Memoria para tests / procesos efímeros (no persistente)
        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        // Recomendado en hosting compartido (persistente y sin extensiones)
        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CACHE_CONNECTION'), // usa la default si es null
            'table' => env('DB_CACHE_TABLE', 'cache'),
            'lock_connection' => env('DB_CACHE_LOCK_CONNECTION'), // usa la default si es null
            'lock_table' => env('DB_CACHE_LOCK_TABLE', 'cache_locks'),
        ],

        // Alternativa sin DB dedicada (menos eficiente al limpiar)
        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            // Laravel usa file locks; mantener en el mismo path simplifica en compartido
            'lock_path' => storage_path('framework/cache/data'),
        ],

        // Opcional: sólo si tenés Memcached habilitado en tu plan
        'memcached' => [
            'driver' => 'memcached',
            'persistent_id' => env('MEMCACHED_PERSISTENT_ID'),
            'sasl' => [
                env('MEMCACHED_USERNAME'),
                env('MEMCACHED_PASSWORD'),
            ],
            'options' => [
                // Ejemplo: tiempo de conexión (descomentá si lo necesitás)
                // Memcached::OPT_CONNECT_TIMEOUT => 2000,
            ],
            'servers' => [
                [
                    'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                    'port' => (int) env('MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
        ],

        // Opcional: sólo si tenés Redis
        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
            'lock_connection' => env('REDIS_CACHE_LOCK_CONNECTION', 'default'),
        ],

        // No recomendado/usable en Hostinger compartido típico, pero lo dejamos definido
        'dynamodb' => [
            'driver' => 'dynamodb',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'table' => env('DYNAMODB_CACHE_TABLE', 'cache'),
            'endpoint' => env('DYNAMODB_ENDPOINT'),
        ],

        // Sólo para Octane (no aplica en compartido)
        'octane' => [
            'driver' => 'octane',
        ],

        // “null” para deshabilitar caché si hiciera falta
        // 'null' => ['driver' => 'null'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | Útil en compartido para evitar colisiones con otras apps que comparten
    | el mismo servidor/DB. Podés personalizarlo por .env: CACHE_PREFIX=...
    |
    */
    'prefix' => env(
        'CACHE_PREFIX',
        Str::slug((string) env('APP_NAME', 'laravel')) . '-cache-'
    ),

];
