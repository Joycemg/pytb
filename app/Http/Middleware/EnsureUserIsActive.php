<?php declare(strict_types=1);

// app/Http/Middleware/EnsureUserIsActive.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserIsActive
{
    /** Rutas que NO deben ser interceptadas (login, registro, etc.) */
    private const ROUTE_WHITELIST = [
        'auth.login',
        'login',
        'auth.logout',
        'logout',
        'auth.register',
        'register',
        'auth.locked',
        'auth.pending',
        'password.request',
        'password.email',
        'password.reset',
        'password.update',
        'verification.notice',
        'verification.verify',
        'verification.send',
    ];

    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();
        if (!$u) {
            return $next($request); // lo maneja 'auth'
        }

        // No interceptar rutas whitelisted
        $current = $request->route()?->getName() ?? '';
        if ($current !== '' && in_array($current, self::ROUTE_WHITELIST, true)) {
            return $next($request);
        }

        // Para APIs/JSON (incluye Inertia por Accept: application/json)
        $wantsJson = $request->expectsJson() || $request->wantsJson() || $request->is('api/*');

        // Cuenta bloqueada
        if ($u->locked_at !== null) {
            if ($wantsJson) {
                return response()->json([
                    'ok' => false,
                    'code' => 'account_locked',
                    'message' => 'Cuenta bloqueada',
                    'locked_at' => optional($u->locked_at)->toIso8601String(),
                ], Response::HTTP_FORBIDDEN);
            }
            return redirect()->route('auth.locked');
        }

        // Cuenta pendiente de aprobación
        if ($u->approved_at === null) {
            if ($wantsJson) {
                return response()->json([
                    'ok' => false,
                    'code' => 'account_pending',
                    'message' => 'Cuenta pendiente de aprobación',
                ], Response::HTTP_FORBIDDEN);
            }
            return redirect()->route('auth.pending');
        }

        return $next($request);
    }
}
