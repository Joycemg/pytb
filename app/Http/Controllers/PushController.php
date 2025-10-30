<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\SendPushPing;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

final class PushController extends Controller
{
    /**
     * Guarda o actualiza la suscripción Web Push del browser.
     */
    public function subscribe(Request $r): JsonResponse
    {
        // Rate limit por IP para que no nos llenen la tabla
        $key = 'push-subscribe:' . $r->ip();
        if (RateLimiter::tooManyAttempts($key, 20)) {
            return response()->json(['error' => 'rate_limited'], 429);
        }
        RateLimiter::hit($key, 60); // ventana 60s

        $data = $r->validate([
            'endpoint' => 'required|string|max:500',
            'keys.p256dh' => 'required|string|max:255',
            'keys.auth' => 'required|string|max:255',
        ]);

        $sub = PushSubscription::updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'p256dh' => $data['keys']['p256dh'] ?? null,
                'auth' => $data['keys']['auth'] ?? null,
                'content_encoding' => 'aes128gcm',
            ]
        );

        // Asociar al usuario logueado si existe
        if ($r->user()) {
            $sub->subscribable()->associate($r->user());
            $sub->save();
        }

        return response()->json(['ok' => true]);
    }

    /**
     * El service worker llama a esto cuando recibe un push "sin payload"
     * para armar la notificación que va a mostrar.
     */
    public function compose(Request $r): JsonResponse
    {
        return response()->json([
            'title' => '🎲 La Taberna',
            'body' => '¡Hay mesas nuevas y cupos disponibles!',
            'icon' => asset('icons/pwa-192.png'),
            'badge' => asset('icons/pwa-192.png'),
            'url' => url('/mesas'),
        ]);
    }

    /**
     * Acción manual de test (sólo staff). Envía un push vacío a todos
     * los endpoints guardados para verificar cuáles siguen vivos.
     */
    public function ping(Request $r): JsonResponse
    {
        $u = $r->user();

        // Autorización defensiva
        if (
            !$u || !(
                method_exists($u, 'hasAnyRole')
                && $u->hasAnyRole(['admin', 'moderator', 'staff'])
            )
        ) {
            abort(403);
        }
        // O si usás Gates:
        // abort_unless(Gate::allows('send-push-test', $u), 403);

        $ids = PushSubscription::query()->pluck('id')->all();

        SendPushPing::dispatch($ids);

        return response()->json([
            'queued' => true,
            'total' => count($ids),
        ]);
    }
}
