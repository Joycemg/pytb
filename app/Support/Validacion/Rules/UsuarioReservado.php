<?php declare(strict_types=1);

namespace App\Support\Validacion\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class UsuarioReservado implements ValidationRule
{
    /** Cache estática por request: evita recomputar arrays si se instancia varias veces */
    private static ?array $cacheReservados = null;

    /** Prefijos reservados (p. ej. “www.”) */
    private const RESERVADOS_PREFIJO = [
        'www',
        'mail',
        'smtp',
        'imap',
        'pop',
        'pop3',
        'ftp',
        'sftp',
        'api',
        'cdn',
        'root',
        'admin',
        'system',
    ];

    /** Lista final (base + extras de .env) en minúsculas */
    private array $reservados;

    public function __construct()
    {
        if (self::$cacheReservados !== null) {
            $this->reservados = self::$cacheReservados;
            return;
        }

        $base = [
            'admin',
            'root',
            'support',
            'help',
            'api',
            'system',
            'staff',
            'moderator',
            'owner',
            'superadmin',
            'postmaster',
            'hostmaster',
            'abuse',
            'info',
            'contact',
            'mailer-daemon',
        ];

        // Añadimos también los prefijos como bloqueos exactos (“www”, “mail”, etc.)
        $base = array_merge($base, self::RESERVADOS_PREFIJO);

        // Extras desde .env (USR_RESERVED_EXTRA="soporte,ventas,admin,root")
        $extra = array_values(array_filter(array_map(
            static fn($v) => mb_strtolower(trim((string) $v), 'UTF-8'),
            explode(',', (string) env('USR_RESERVED_EXTRA', ''))
        )));

        self::$cacheReservados = array_values(array_unique(array_merge($base, $extra)));
        $this->reservados = self::$cacheReservados;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $raw = (string) $value;
        $v = mb_strtolower(trim($raw), 'UTF-8');

        // 1) Longitud y charset: 3–20, solo [a-z0-9_.]
        if (!preg_match('/^[a-z0-9_.]{3,20}$/', $v)) {
            $fail('El :attribute debe tener entre 3 y 20 caracteres y usar solo letras minúsculas, números, "_" o ".".');
            return;
        }

        // 2) No iniciar/terminar con "." o "_"
        if ($v[0] === '.' || $v[0] === '_' || str_ends_with($v, '.') || str_ends_with($v, '_')) {
            $fail('El :attribute no puede comenzar ni terminar con "." o "_".');
            return;
        }

        // 3) Evitar repeticiones problemáticas
        if (str_contains($v, '..') || str_contains($v, '__') || str_contains($v, '._') || str_contains($v, '_.')) {
            $fail('El :attribute no puede contener combinaciones consecutivas de "." o "_".');
            return;
        }

        // 4) Prefijos reservados (www., mail., etc.)
        foreach (self::RESERVADOS_PREFIJO as $pref) {
            if (str_starts_with($v, $pref . '.')) {
                $fail('El :attribute no puede comenzar con un prefijo reservado como "' . $pref . '.".');
                return;
            }
        }

        // 5) Palabras reservadas exactas
        if (in_array($v, $this->reservados, true)) {
            $fail('El :attribute elegido está reservado.');
            return;
        }

        // 6) Reservadas tras quitar separadores (evita "ad.min", "ro_ot", etc.)
        $compact = str_replace(['.', '_'], '', $v);
        if (in_array($compact, $this->reservados, true)) {
            $fail('El :attribute elegido es demasiado similar a un nombre reservado.');
            return;
        }

        // 7) Sólo numérico tras quitar separadores
        if (ctype_digit($compact)) {
            $fail('El :attribute no puede ser únicamente numérico.');
            return;
        }
    }
}
