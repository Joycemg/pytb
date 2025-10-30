<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Gate;

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

        $subs = PushSubscription::query()->get();

        $sent = 0;
        $deleted = 0;

        foreach ($subs as $s) {
            $status = $this->sendNoPayloadPush($s->endpoint);

            if (in_array($status, [201, 202, 204], true)) {
                $sent++;
            } elseif (in_array($status, [404, 410], true)) {
                // endpoint inválido, limpiamos DB
                $s->delete();
                $deleted++;
            }
        }

        return response()->json([
            'sent' => $sent,
            'deleted' => $deleted,
            'total' => $subs->count(), // count post-delete -> "quedaron"
        ]);
    }

    /**
     * Envía un Web Push "sin payload" (lo que dispara que el service worker
     * haga un fetch a /push/compose para armar el cuerpo real).
     */
    private function sendNoPayloadPush(string $endpoint): int
    {
        $vapidPub = env('WEBPUSH_VAPID_PUBLIC_KEY');
        $vapidPrivPem = env('WEBPUSH_VAPID_PRIVATE_KEY');
        $subject = env('WEBPUSH_SUBJECT', 'mailto:admin@example.com');

        if (!$vapidPub || !$vapidPrivPem) {
            // Falta la config => no intentamos, devolvemos 0
            return 0;
        }

        $aud = $this->originFromUrl($endpoint);
        $jwt = $this->makeVapidJwt($aud, $subject, $vapidPrivPem);

        $headers = [
            'TTL: 2419200', // 28 días
            'Authorization: vapid t=' . $jwt . ', k=' . $vapidPub,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_POSTFIELDS => '', // "no payload" => el SW luego hace fetch compose()
            CURLOPT_TIMEOUT => 10,
        ]);

        curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;
        curl_close($ch);

        return $status;
    }

    private function originFromUrl(string $u): string
    {
        $p = parse_url($u);
        $scheme = $p['scheme'] ?? 'https';
        $host = $p['host'] ?? '';
        $port = isset($p['port']) ? ':' . $p['port'] : '';
        return $scheme . '://' . $host . $port;
    }

    /**
     * Genera el JWT VAPID ES256 con expiración corta.
     */
    private function makeVapidJwt(string $aud, string $sub, string $privatePem): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'ES256'];
        $payload = [
            'aud' => $aud,
            'exp' => time() + 12 * 60 * 60, // 12h
            'sub' => $sub,
        ];

        $b64 = static function (array $x): string {
            $json = json_encode($x, JSON_UNESCAPED_SLASHES);
            $b64u = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
            return $b64u;
        };

        $b64Header = $b64($header);
        $b64Payload = $b64($payload);
        $toSign = $b64Header . '.' . $b64Payload;

        $pkey = openssl_pkey_get_private($privatePem);
        if (!$pkey) {
            throw new \RuntimeException('WEBPUSH_VAPID_PRIVATE_KEY inválida o mal formateada');
        }

        $signature = '';
        openssl_sign($toSign, $signature, $pkey, OPENSSL_ALGO_SHA256);
        openssl_free_key($pkey);

        // Transformar firma DER -> 64 bytes R||S
        $rawSig = $this->ecdsaDerToConcat($signature, 64);

        $b64Sig = rtrim(strtr(base64_encode($rawSig), '+/', '-_'), '=');

        return $toSign . '.' . $b64Sig;
    }

    /**
     * Convierte la firma ECDSA DER en una concatenada R||S de longitud fija.
     */
    private function ecdsaDerToConcat(string $der, int $len): string
    {
        if (ord($der[0]) !== 0x30) {
            throw new \RuntimeException('Firma DER inválida');
        }

        $off = 2;

        if (ord($der[$off]) !== 0x02) {
            throw new \RuntimeException('DER R inválido');
        }
        $rLen = ord($der[$off + 1]);
        $r = substr($der, $off + 2, $rLen);
        $off += 2 + $rLen;

        if (ord($der[$off]) !== 0x02) {
            throw new \RuntimeException('DER S inválido');
        }
        $sLen = ord($der[$off + 1]);
        $s = substr($der, $off + 2, $sLen);

        // Sacar ceros de padding al frente
        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");

        // R y S deben ser mitad-mitad del total
        $half = (int) ($len / 2);
        $r = str_pad($r, $half, "\x00", STR_PAD_LEFT);
        $s = str_pad($s, $half, "\x00", STR_PAD_LEFT);

        return $r . $s;
    }
}
