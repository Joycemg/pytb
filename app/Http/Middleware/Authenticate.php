<?php declare(strict_types=1);

// app/Http/Middleware/Authenticate.php
namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

final class Authenticate extends Middleware
{
    /**
     * URL de redirección cuando falta autenticación.
     * - API/JSON: null → el core responde 401 sin redirigir.
     * - HTML: intenta 'auth.login' → 'login' → '/login'.
     * - Evita loops si ya estás en login.
     * - (Opcional) agrega ?redirect=<url actual>.
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson() || $request->wantsJson() || $request->is('api/*')) {
            return null;
        }

        // Evitar bucles si ya estamos en login
        $current = Route::currentRouteName() ?? '';
        if (in_array($current, ['auth.login', 'login'], true)) {
            return null;
        }

        $target = null;
        if (Route::has('auth.login')) {
            $target = route('auth.login');
        } elseif (Route::has('login')) {
            $target = route('login');
        } else {
            $target = url('/login');
        }

        // Adjuntar redirect de manera segura (no rompe si es muy largo)
        $intended = $request->fullUrl();
        return $target . (str_contains($target, '?') ? '&' : '?') . 'redirect=' . urlencode($intended);
    }
}
