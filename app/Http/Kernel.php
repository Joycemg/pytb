<?php declare(strict_types=1);

// app/Http/Kernel.php
namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

final class Kernel extends HttpKernel
{
    /**
     * Middleware global (toda petición).
     * Ordenado para proxys/CDN típicos de Hostinger/Cloudflare.
     */
    protected $middleware = [
        \App\Http\Middleware\TrustHosts::class,
        \App\Http\Middleware\TrustProxies::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Http\Middleware\TrustProxies::class
    ];

    /**
     * Grupos
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,

            // ETag al final del pipeline web (evita computar en errores/streams)
            \App\Http\Middleware\ETagDebil::class,
        ],

        'api' => [
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            // Para ETag en endpoints específicos: usar alias 'etag' por ruta
        ],
    ];

    /**
     * Aliases (Laravel 11+)
     */
    protected $middlewareAliases = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'active' => \App\Http\Middleware\EnsureUserIsActive::class,
        'etag' => \App\Http\Middleware\ETagDebil::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        // 'signed'      => \Illuminate\Routing\Middleware\ValidateSignature::class,
        // 'verified'    => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
    ];
}
