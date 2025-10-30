<?php declare(strict_types=1);

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Collection;

final class WebPushSender
{
    /**
     * Procesa un lote de suscripciones, enviando un push vacío a cada una
     * y eliminando los endpoints inválidos.
     *
     * @return array{sent:int,deleted:int,total:int}
     */
    public function sendBatch(Collection $subscriptions): array
    {
        $result = [
            'sent' => 0,
            'deleted' => 0,
            'total' => 0,
        ];

        foreach ($subscriptions as $subscription) {
            $result['total']++;

            $status = $this->sendSubscription($subscription);

            if (in_array($status, [201, 202, 204], true)) {
                $result['sent']++;
            } elseif (in_array($status, [404, 410], true)) {
                $subscription->delete();
                $result['deleted']++;
            }
        }

        return $result;
    }

    protected function sendSubscription(PushSubscription $subscription): int
    {
        $vapidPub = env('WEBPUSH_VAPID_PUBLIC_KEY');
        $vapidPrivPem = env('WEBPUSH_VAPID_PRIVATE_KEY');
        $subject = env('WEBPUSH_SUBJECT', 'mailto:admin@example.com');

        if (!$vapidPub || !$vapidPrivPem) {
            return 0;
        }

        $endpoint = (string) $subscription->endpoint;
        $aud = $this->originFromUrl($endpoint);
        $jwt = $this->makeVapidJwt($aud, $subject, $vapidPrivPem);

        $headers = [
            'TTL: 2419200',
            'Authorization: vapid t=' . $jwt . ', k=' . $vapidPub,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_POSTFIELDS => '',
            CURLOPT_TIMEOUT => 10,
        ]);

        curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;
        curl_close($ch);

        return $status;
    }

    private function originFromUrl(string $url): string
    {
        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return $scheme . '://' . $host . $port;
    }

    private function makeVapidJwt(string $audience, string $subject, string $privateKeyPem): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'ES256'];
        $payload = [
            'aud' => $audience,
            'exp' => time() + 12 * 60 * 60,
            'sub' => $subject,
        ];

        $b64 = static function (array $data): string {
            $json = json_encode($data, JSON_UNESCAPED_SLASHES);
            return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        };

        $b64Header = $b64($header);
        $b64Payload = $b64($payload);
        $toSign = $b64Header . '.' . $b64Payload;

        $pkey = openssl_pkey_get_private($privateKeyPem);
        if (!$pkey) {
            throw new \RuntimeException('WEBPUSH_VAPID_PRIVATE_KEY inválida o mal formateada');
        }

        $signature = '';
        openssl_sign($toSign, $signature, $pkey, OPENSSL_ALGO_SHA256);
        openssl_free_key($pkey);

        $rawSig = $this->ecdsaDerToConcat($signature, 64);
        $b64Sig = rtrim(strtr(base64_encode($rawSig), '+/', '-_'), '=');

        return $toSign . '.' . $b64Sig;
    }

    private function ecdsaDerToConcat(string $der, int $length): string
    {
        if ($der === '' || ord($der[0]) !== 0x30) {
            throw new \RuntimeException('Firma DER inválida');
        }

        $offset = 2;

        if (ord($der[$offset]) !== 0x02) {
            throw new \RuntimeException('DER R inválido');
        }
        $rLength = ord($der[$offset + 1]);
        $r = substr($der, $offset + 2, $rLength);
        $offset += 2 + $rLength;

        if (ord($der[$offset]) !== 0x02) {
            throw new \RuntimeException('DER S inválido');
        }
        $sLength = ord($der[$offset + 1]);
        $s = substr($der, $offset + 2, $sLength);

        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");

        $half = (int) ($length / 2);
        $r = str_pad($r, $half, "\x00", STR_PAD_LEFT);
        $s = str_pad($s, $half, "\x00", STR_PAD_LEFT);

        return $r . $s;
    }
}
