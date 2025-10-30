<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class ApiController extends Controller
{
    /** Límite de rate limit (intentos) */
    private const RL_MAX_ATTEMPTS = 30;
    /** Ventana del rate limit en segundos */
    private const RL_DECAY_SECONDS = 60;
    /** Cache control del ping (segundos) por default (overridable por config) */
    private const PING_TTL = 30;
    /** Métodos permitidos para /ping */
    private const ALLOWED_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * Healthcheck con ETag y rate limiting (hosting compartido).
     * Acepta GET/HEAD/OPTIONS (CORS simple). Para otros métodos, 405.
     */
    public function ping(Request $req): Response|JsonResponse
    {
        $method = strtoupper($req->getMethod());
        $reqId = (string) Str::uuid();

        // CORS preflight (aunque GET/HEAD no requieren, algunos clientes mandan OPTIONS)
        if ($method === 'OPTIONS') {
            return response('', Response::HTTP_NO_CONTENT)->withHeaders(
                $this->baseHeaders($reqId) + $this->corsHeaders($req)
            );
        }

        if (!in_array($method, self::ALLOWED_METHODS, true)) {
            return response()
                ->json(['message' => 'Method Not Allowed'], Response::HTTP_METHOD_NOT_ALLOWED, $this->jsonHeaders())
                ->withHeaders([
                    'Allow' => implode(', ', self::ALLOWED_METHODS),
                ] + $this->baseHeaders($reqId) + $this->corsHeaders($req));
        }

        // ── Rate limiting por usuario o huella anónima ───────────────────────
        $fingerprint = $req->user()?->id
            ?? sha1(($req->ip() ?? '0.0.0.0') . '|' . ($req->userAgent() ?? 'na'));
        $key = 'api:ping:' . $fingerprint;

        if (RateLimiter::tooManyAttempts($key, self::RL_MAX_ATTEMPTS)) {
            $retry = RateLimiter::availableIn($key);

            return response()
                ->json(['message' => 'Too Many Requests'], Response::HTTP_TOO_MANY_REQUESTS, $this->jsonHeaders())
                ->withHeaders(
                    $this->baseHeaders($reqId)
                    + $this->corsHeaders($req)
                    + $this->rateHeaders($key, true)
                    + ['Retry-After' => (string) $retry]
                );
        }

        RateLimiter::hit($key, self::RL_DECAY_SECONDS);

        // ── Payload ──────────────────────────────────────────────────────────
        $now = now();
        $payload = [
            'pong' => true,
            'at' => $now->toIso8601String(),
            'app' => (string) config('app.name'),
            'env' => (string) config('app.env'),
            // opcional: versionado para invalidar caches en deploys
            'ver' => (string) (config('app.version') ?? ''),
        ];

        // ── ETag débil: slot de TTL para alinear con max-age ────────────────
        $ttl = (int) config('app.ping_ttl', self::PING_TTL);
        $slot = intdiv($now->getTimestamp(), max(1, $ttl)); // cambia cada ttl
        $rawEtag = sha1(json_encode($payload, JSON_UNESCAPED_UNICODE) . '|' . $slot);
        $etag = 'W/"' . $rawEtag . '"';

        $headersBase = $this->baseHeaders($reqId) + $this->corsHeaders($req);

        // If-None-Match (acepta múltiple y W/)
        $ifNoneMatch = $req->headers->get('If-None-Match');
        if ($ifNoneMatch && $this->etagMatches($ifNoneMatch, $etag)) {
            return response('', Response::HTTP_NOT_MODIFIED)->withHeaders(
                $headersBase
                + $this->rateHeaders($key)
                + [
                    'ETag' => $etag,
                    'Cache-Control' => 'private, max-age=' . $ttl . ', must-revalidate',
                    'Vary' => $this->varyHeader($headersBase),
                ]
            );
        }

        if ($method === 'HEAD') {
            return response('', Response::HTTP_OK)->withHeaders(
                $headersBase
                + $this->rateHeaders($key)
                + [
                    'ETag' => $etag,
                    'Cache-Control' => 'private, max-age=' . $ttl . ', must-revalidate',
                    'Vary' => $this->varyHeader($headersBase),
                    'Content-Type' => 'application/json; charset=UTF-8',
                    'Content-Length' => '0',
                ]
            );
        }

        return response()
            ->json($payload, Response::HTTP_OK, $this->jsonHeaders())
            ->withHeaders(
                $headersBase
                + $this->rateHeaders($key)
                + [
                    'ETag' => $etag,
                    'Cache-Control' => 'private, max-age=' . $ttl . ', must-revalidate',
                    'Vary' => $this->varyHeader($headersBase),
                ]
            );
    }

    /**
     * Compara If-None-Match (posibles múltiples ETags) con nuestro ETag.
     * Acepta validadores débiles (W/) y normaliza comillas/espacios.
     */
    private function etagMatches(string $ifNoneMatch, string $ourEtag): bool
    {
        $normalize = static fn(string $t) => Str::of(trim($t))
            ->replaceStart('W/', '')
            ->trim()
            ->trim('"')
            ->toString();

        $ours = $normalize($ourEtag);
        if (trim($ifNoneMatch) === '*') {
            return true;
        }

        foreach (explode(',', $ifNoneMatch) as $candidate) {
            if ($normalize($candidate) === $ours) {
                return true;
            }
        }
        return false;
    }

    /** Headers base comunes (sin CORS ni rate-limit) */
    private function baseHeaders(string $reqId): array
    {
        return [
            'X-Request-Id' => $reqId,
            // Vary lo componemos al final; acá ponemos lo estable
            'Vary' => 'Authorization, Accept-Encoding',
        ];
    }

    /** CORS mínimo (allowlist + credenciales opcionales por config) */
    private function corsHeaders(Request $req): array
    {
        $origin = $req->headers->get('Origin');
        $allow = config('app.cors_allowlist', []); // e.g. ['https://tu-dominio.com']
        $withCreds = (bool) config('app.cors_credentials', false);
        $headers = [];

        if (is_array($allow) && $origin && in_array($origin, $allow, true)) {
            $headers['Access-Control-Allow-Origin'] = $origin;
            if ($withCreds) {
                $headers['Access-Control-Allow-Credentials'] = 'true';
            }
            $headers['Vary'] = ($headers['Vary'] ?? '') . (empty($headers['Vary']) ? '' : ', ') . 'Origin';
        } elseif ($allow === '*' || (is_string($allow) && $allow === '*')) {
            // si querés abrir a todos (sin credentials)
            $headers['Access-Control-Allow-Origin'] = '*';
            // con '*' NO se deben enviar credenciales
        }

        // Métodos y headers para preflight amistoso
        $headers['Access-Control-Allow-Methods'] = 'GET, HEAD, OPTIONS';
        $reqHdrs = $req->headers->get('Access-Control-Request-Headers');
        if ($reqHdrs) {
            $headers['Access-Control-Allow-Headers'] = $reqHdrs;
        } else {
            $headers['Access-Control-Allow-Headers'] = 'Content-Type, Authorization';
        }
        $headers['Access-Control-Max-Age'] = '600'; // 10 min

        return $headers;
    }

    /** Cabeceras de rate-limit consistentes (incluye reset en segundos hasta liberar) */
    private function rateHeaders(string $key, bool $whenLimited = false): array
    {
        $remaining = RateLimiter::remaining($key, self::RL_MAX_ATTEMPTS);
        $reset = RateLimiter::availableIn($key);

        return [
            'X-RateLimit-Limit' => (string) self::RL_MAX_ATTEMPTS,
            'X-RateLimit-Remaining' => $whenLimited ? '0' : (string) $remaining,
            'X-RateLimit-Reset' => (string) $reset,
        ];
    }

    /** Content-Type JSON estándar */
    private function jsonHeaders(): array
    {
        return ['Content-Type' => 'application/json; charset=UTF-8'];
    }

    /** Compone Vary evitando duplicados */
    private function varyHeader(array $headersBase): string
    {
        $vary = $headersBase['Vary'] ?? 'Authorization, Accept-Encoding';
        // si corsHeaders agregó Origin, ya está concatenado
        return $vary;
    }
}
