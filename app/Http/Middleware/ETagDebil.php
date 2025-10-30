<?php declare(strict_types=1);

// app/Http/Middleware/ETagDebil.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ETagDebil
{
    /** Tamaño máximo a hashear (bytes) para evitar costo en compartido */
    private const MAX_BYTES = 1_500_000; // ~1.5MB

    /** Tipos de contenido a considerar para ETag */
    private const ALLOWED_PREFIXES = [
        'text/',
        'application/json',
        'application/javascript',
        'application/xml',
    ];

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        /** @var SymfonyResponse $response */
        $response = $next($request);

        // Sólo GET/HEAD
        $method = $request->getMethod();
        if ($method !== 'GET' && $method !== 'HEAD') {
            return $response;
        }

        // Si se explicitó no cachear o ya hay ETag/Last-Modified, salir
        if (
            $response->headers->has('ETag')
            || $response->headers->has('Last-Modified')
            || str_contains(strtolower((string) $response->headers->get('Cache-Control', '')), 'no-store')
        ) {
            return $response;
        }

        // No streams/archivos
        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return $response;
        }

        // Solo 2xx, excluyendo 204
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300 || $status === 204) {
            return $response;
        }

        // Content-Type compatible
        $ct = strtolower((string) $response->headers->get('Content-Type', ''));
        $isAllowed = false;
        foreach (self::ALLOWED_PREFIXES as $p) {
            if (str_starts_with($ct, $p)) {
                $isAllowed = true;
                break;
            }
        }
        if (!$isAllowed) {
            return $response;
        }

        // Cuerpo manejable
        $content = (string) $response->getContent(); // seguro: no streams
        $len = strlen($content);
        if ($len === 0 || $len > self::MAX_BYTES) {
            return $response;
        }

        // ETag débil
        $hash = hash('sha1', $content);
        $response->setEtag($hash, true); // weak → W/"..."

        // Cache-Control por defecto (seguro para compartido)
        if (!$response->headers->has('Cache-Control')) {
            $response->headers->set('Cache-Control', 'private, max-age=0, must-revalidate');
        }

        // Vary sin duplicados
        $vary = array_map('trim', array_filter(explode(',', (string) $response->headers->get('Vary', ''))));
        foreach (['Authorization', 'Accept-Encoding'] as $needed) {
            if (!in_array($needed, $vary, true)) {
                $vary[] = $needed;
            }
        }
        $response->headers->set('Vary', implode(', ', $vary));

        // 304 si coincide If-None-Match
        if ($response->isNotModified($request)) {
            $response->setNotModified();
        }

        return $response;
    }
}
