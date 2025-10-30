<?php declare(strict_types=1);

namespace App\Services;

/**
 * Descarga segura y ligera de imágenes remotas (JPEG/PNG) para hosting compartido.
 *
 * @return array{mime:string,data:string,width:int,height:int}|null
 */
final class DescargaImagenRemota
{
    public static function descargar(
        string $url,
        int $maxBytes = 1_048_576, // 1 MiB
        int $minW = 48,
        int $minH = 48
    ): ?array {
        $u = \parse_url($url);
        if (!$u || empty($u['host'])) {
            return null;
        }

        $scheme = \strtolower($u['scheme'] ?? '');
        if (!\in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        if (isset($u['port']) && !\in_array((int) $u['port'], [80, 443], true)) {
            return null;
        }

        if (!self::allResolvedIpsArePublic($u['host'])) {
            return null;
        }

        $data = \function_exists('curl_init')
            ? self::curlFetch($url, $maxBytes)
            : self::streamFetch($url, $maxBytes);

        if ($data === null || $data === '') {
            return null;
        }

        $f = new \finfo(\FILEINFO_MIME_TYPE);
        $mime = $f->buffer($data) ?: 'application/octet-stream';
        if (!\in_array($mime, ['image/jpeg', 'image/png'], true)) {
            return null;
        }

        $info = @\getimagesizefromstring($data);
        if (!$info) {
            return null;
        }
        [$w, $h] = [(int) ($info[0] ?? 0), (int) ($info[1] ?? 0)];
        if ($w < $minW || $h < $minH) {
            return null;
        }

        return ['mime' => $mime, 'data' => $data, 'width' => $w, 'height' => $h];
    }

    private static function curlFetch(string $url, int $maxBytes): ?string
    {
        $ch = \curl_init($url);
        if ($ch === false) {
            return null;
        }

        $buf = '';
        \curl_setopt_array($ch, [
            \CURLOPT_FOLLOWLOCATION => false,
            \CURLOPT_MAXREDIRS => 2,
            \CURLOPT_HTTPGET => true,
            \CURLOPT_RETURNTRANSFER => false,
            \CURLOPT_TIMEOUT => 8,
            \CURLOPT_CONNECTTIMEOUT => 5,
            \CURLOPT_USERAGENT => 'LaTaberna/1.0',
            \CURLOPT_HTTPHEADER => ['Accept: image/*'],
            \CURLOPT_SSL_VERIFYPEER => true,
            \CURLOPT_SSL_VERIFYHOST => 2,
            \CURLOPT_PROTOCOLS => \CURLPROTO_HTTP | \CURLPROTO_HTTPS,
            \CURLOPT_REDIR_PROTOCOLS => \CURLPROTO_HTTP | \CURLPROTO_HTTPS,
            \CURLOPT_HEADERFUNCTION => static function ($ch, string $header) use ($maxBytes) {
                if (\stripos($header, 'Content-Length:') === 0) {
                    $len = (int) \trim(\substr($header, 15));
                    if ($len > 0 && $len > $maxBytes) {
                        return 0; // abortar
                    }
                }
                return \strlen($header);
            },
            \CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$buf, $maxBytes) {
                $buf .= $chunk; // concatenación correcta
                if (\strlen($buf) > $maxBytes) {
                    return 0; // abortar temprano
                }
                return \strlen($chunk);
            },
        ]);

        @\curl_exec($ch);
        $err = \curl_errno($ch);
        $status = (int) \curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        \curl_close($ch);

        if ($err !== 0 || $status < 200 || $status >= 300) {
            return null;
        }

        return $buf;
    }

    private static function streamFetch(string $url, int $maxBytes): ?string
    {
        // HEAD “manual” con get_headers para chequear redirecciones y Content-Length
        $headers = @\get_headers($url, true);
        if (\is_array($headers)) {
            $redirCount = 0;
            while (isset($headers['Location'])) {
                $redirCount++;
                if ($redirCount > 2) {
                    return null;
                }
                $url = \is_array($headers['Location'])
                    ? (string) \end($headers['Location'])
                    : (string) $headers['Location'];

                $u = \parse_url($url);
                if (
                    !$u || empty($u['host'])
                    || !\in_array(\strtolower($u['scheme'] ?? ''), ['http', 'https'], true)
                ) {
                    return null;
                }
                if (!self::allResolvedIpsArePublic($u['host'])) {
                    return null;
                }
                $headers = @\get_headers($url, true);
                if (!\is_array($headers)) {
                    break;
                }
            }
            $len = null;
            if (isset($headers['Content-Length'])) {
                $len = \is_array($headers['Content-Length'])
                    ? (int) \end($headers['Content-Length'])
                    : (int) $headers['Content-Length'];
            }
            if (!empty($len) && $len > $maxBytes) {
                return null;
            }
        }

        $opts = [
            'http' => [
                'method' => 'GET',
                'timeout' => 8,
                'ignore_errors' => true,
                'follow_location' => 0,
                'header' => "User-Agent: LaTaberna/1.0\r\nAccept: image/*\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ];

        $ctx = \stream_context_create($opts);
        $fh = @\fopen($url, 'rb', false, $ctx);
        if (!$fh) {
            return null;
        }

        @\stream_set_timeout($fh, 8);

        // Re-chequeo de Content-Length en la respuesta final
        $meta = \stream_get_meta_data($fh);
        $wrapper = (array) ($meta['wrapper_data'] ?? []);
        foreach ($wrapper as $hdr) {
            if (\stripos((string) $hdr, 'Content-Length:') === 0) {
                $len = (int) \trim(\substr((string) $hdr, 15));
                if ($len > 0 && $len > $maxBytes) {
                    \fclose($fh);
                    return null;
                }
                break;
            }
        }

        $data = '';
        while (!\feof($fh)) {
            $chunk = \fread($fh, 8192);
            if ($chunk === false) {
                break;
            }
            if ($chunk === '') {
                break; // EOF defensivo
            }

            $data .= $chunk; // FIX: concatenación correcta

            if (\strlen($data) > $maxBytes) {
                \fclose($fh);
                return null; // abortar si supera el límite
            }
        }
        \fclose($fh);

        if ($data === '') {
            return null;
        }

        return $data;
    }

    private static function allResolvedIpsArePublic(string $host): bool
    {
        $ipv4s = @\gethostbynamel($host) ?: [];
        foreach ($ipv4s as $ip) {
            if (!self::isPublicIp($ip)) {
                return false;
            }
        }

        $aaaa = [];
        if (\function_exists('dns_get_record')) {
            $aaaa = @\dns_get_record($host, \DNS_AAAA) ?: [];
            foreach ($aaaa as $rec) {
                if (!empty($rec['ipv6']) && !self::isPublicIp($rec['ipv6'])) {
                    return false;
                }
            }
        }

        return !empty($ipv4s) || !empty($aaaa);
    }

    private static function isPublicIp(string $ip): bool
    {
        return \filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
