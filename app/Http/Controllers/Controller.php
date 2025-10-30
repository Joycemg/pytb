<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /** Headers JSON por defecto (ligeros y seguros para hosting compartido) */
    private const JSON_HEADERS = ['Content-Type' => 'application/json; charset=UTF-8'];

    /* ======================= Respuestas JSON ======================= */

    /**
     * Respuesta JSON estándar de éxito.
     * Mantiene firma y comportamiento, agrega flags robustos al JSON.
     */
    protected function ok(array $data = [], int $status = 200, array $headers = []): JsonResponse
    {
        return response()->json(
            ['ok' => true] + $data,
            $status,
            self::JSON_HEADERS + $headers,
            $this->jsonOptions()
        );
    }

    /**
     * Respuesta JSON estándar de error.
     * Incluye mensaje y permite datos extra.
     */
    protected function fail(string $message, int $status = 422, array $extra = []): JsonResponse
    {
        return response()->json(
            ['ok' => false, 'message' => $message] + $extra,
            $status,
            self::JSON_HEADERS,
            $this->jsonOptions()
        );
    }

    /**
     * Variante de ok() con caché ligera (ej. healthchecks rápidos).
     */
    protected function okCached(array $data = [], int $ttlSeconds = 30): JsonResponse
    {
        return $this->ok($data, 200, [
            'Cache-Control' => "private, max-age={$ttlSeconds}, must-revalidate",
            'Vary' => 'Authorization, Accept-Encoding',
        ]);
    }

    /**
     * 204 sin contenido (opcional; no afecta a quien no lo use).
     */
    protected function noContent(array $headers = []): JsonResponse
    {
        return response()->json(null, 204, $headers + ['Cache-Control' => 'no-store, max-age=0']);
    }

    /**
     * Helper para respuestas paginadas consistentes.
     * Devuelve { ok, data, meta: { current_page, per_page, total, last_page } }
     */
    protected function okPaginated(LengthAwarePaginator $paginator, array $extra = [], int $status = 200, array $headers = []): JsonResponse
    {
        return $this->ok([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ] + $extra, $status, $headers);
    }

    /* ───────────────────────
     |  Parsers/normalizadores
     ─────────────────────── */

    /**
     * Entero estricto; acepta " 123 " pero NO "12e3"/"123abc".
     * (min/max son opcionales y mantienen compatibilidad hacia atrás).
     */
    protected function intOr(Request $r, string $key, int $default = 0, ?int $min = null, ?int $max = null): int
    {
        $raw = trim((string) $r->input($key, ''));
        if ($raw === '' || preg_match('/^[+-]?\d+$/', $raw) !== 1) {
            return $default;
        }
        $val = (int) $raw;
        if ($min !== null && $val < $min)
            $val = $min;
        if ($max !== null && $val > $max)
            $val = $max;
        return $val;
    }

    /**
     * Booleans de checkboxes/flags.
     * "1,true,on,yes,si,sí,verdadero" → true ; "0,false,off,no,falso" → false; resto = $default.
     */
    protected function boolOr(Request $r, string $key, bool $default = false): bool
    {
        $v = $r->input($key);

        if (is_bool($v))
            return $v;
        if (is_numeric($v))
            return ((int) $v) === 1;

        $s = mb_strtolower(trim((string) $v), 'UTF-8');
        if ($s === '')
            return $default;

        if (in_array($s, ['1', 'true', 'on', 'yes', 'si', 'sí', 'verdadero'], true))
            return true;
        if (in_array($s, ['0', 'false', 'off', 'no', 'falso'], true))
            return false;

        return $default;
    }

    /**
     * String “limpio” (sin espacios extra). Devuelve $default si viene vacío.
     * $maxLen es opcional y no cambia compatibilidad si no se usa.
     */
    protected function strOr(Request $r, string $key, string $default = '', ?int $maxLen = null): string
    {
        $s = trim((string) $r->input($key, ''));
        if ($s === '')
            return $default;

        $s = preg_replace('/\s+/', ' ', $s) ?? $s;

        if ($maxLen !== null && $maxLen > 0) {
            $s = Str::limit($s, $maxLen, '');
        }

        return $s;
    }

    /**
     * Flags JSON: unicode sin escapar + sustitución de UTF-8 inválido.
     * En local, pretty-print para debug (no afecta prod).
     */
    private function jsonOptions(): int
    {
        $opts = JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;
        if (app()->isLocal()) {
            $opts |= JSON_PRETTY_PRINT;
        }
        return $opts;
    }

    /* =================== Extras reusables (no rompen nada) =================== */

    /** JSON con ETag débil alineado al TTL. Si coincide If-None-Match → 304. */
    protected function okEtagged(Request $r, array $data, int $ttlSeconds = 30, ?string $versionSalt = null): JsonResponse
    {
        $now = Carbon::now();
        $bucket = intdiv($now->getTimestamp(), max(1, $ttlSeconds));
        $raw = json_encode($data, JSON_UNESCAPED_UNICODE) . '|' . $bucket . '|' . ($versionSalt ?? '');
        $etag = 'W/"' . sha1($raw) . '"';

        $ifNone = (string) $r->headers->get('If-None-Match', '');
        $normalize = static fn(string $t) => Str::of(trim($t))->replaceStart('W/', '')->trim()->trim('"')->toString();
        if ($ifNone !== '') {
            $ours = $normalize($etag);
            foreach (explode(',', $ifNone) as $cand) {
                if ($normalize($cand) === $ours) {
                    return response()->json(null, 304, [
                        'ETag' => $etag,
                        'Cache-Control' => "private, max-age={$ttlSeconds}, must-revalidate",
                        'Vary' => 'Authorization, Accept-Encoding',
                    ], $this->jsonOptions());
                }
            }
        }
        return $this->ok($data, 200, [
            'ETag' => $etag,
            'Cache-Control' => "private, max-age={$ttlSeconds}, must-revalidate",
            'Vary' => 'Authorization, Accept-Encoding',
        ]);
    }

    /** LIKE seguro (%term%), escapando %_. */
    protected function likeTerm(string $needle, int $maxLen = 80): string
    {
        $needle = mb_substr(trim($needle), 0, $maxLen);
        return '%' . addcslashes($needle, '%_') . '%';
    }

    /** per_page a salvo (10–100, por defecto 30). */
    protected function perPageFrom(Request $r, int $default = 30): int
    {
        $pp = $this->intOr($r, 'per_page', $default, 10, 100);
        return $pp;
    }

    /** Array de enteros sanitizado (útil para bulk ids[]). */
    protected function intArrayOr(Request $r, string $key, int $maxItems = 500, int $min = 1, ?int $max = null): array
    {
        $arr = $r->input($key, []);
        if (!is_array($arr))
            return [];
        $out = [];
        foreach ($arr as $v) {
            if (is_numeric($v) && preg_match('/^[+-]?\d+$/', (string) $v) === 1) {
                $n = (int) $v;
                if ($n < $min)
                    continue;
                if ($max !== null && $n > $max)
                    continue;
                $out[] = $n;
            }
            if (count($out) >= $maxItems)
                break;
        }
        return array_values(array_unique($out));
    }

    /** TZ preferida (display → app → UTC). */
    protected function appTz(): string
    {
        return (string) config('app.display_timezone', config('app.timezone', 'UTC'));
    }

    /** Parse de fecha con TZ de la app (inicio/fin del día). */
    protected function dateOr(?string $val, bool $endOfDay = false): ?Carbon
    {
        if (!filled($val))
            return null;
        try {
            $dt = Carbon::parse($val, $this->appTz());
            return $endOfDay ? $dt->endOfDay() : $dt->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /** CSV safe (evita CR/LF e inyección de fórmulas). */
    protected function csvSafe(?string $v): string
    {
        $s = (string) $v;
        $s = str_replace(["\r", "\n"], [' ', ' '], $s);
        if ($s !== '' && strpbrk($s[0], "=+-@") !== false)
            $s = "'" . $s;
        return $s;
    }
}
