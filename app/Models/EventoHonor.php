<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Modelo de eventos de honor.
 *
 * Pensado para hosting compartido:
 * - Sin timestamps automáticos.
 * - Chequeos de columnas cacheados en estáticos.
 * - Upserts idempotentes por (user_id, slug).
 * - Truncado seguro de slug (índices utf8mb4 antiguos).
 */
final class EventoHonor extends Model
{
    use HasFactory;

    /** Nombre de la tabla */
    protected $table = 'eventos_honor';

    /** Esta tabla típicamente no usa created_at/updated_at */
    public $timestamps = false;

    /** Límite seguro para índices en MySQL antiguos (utf8mb4_191) */
    private const SLUG_MAX = 191;
    private const REASON_MAX = 1000;

    /**
     * Campos permitidos:
     * - Soporta esquemas con 'nota' o 'reason' (mutators mapean a 'nota').
     */
    protected $fillable = [
        'user_id',
        'mesa_id',
        'slug',
        'delta',
        'nota',
        'reason',
        'is_counted',
        'occurred_at',
    ];

    /** Casts livianos */
    protected $casts = [
        'user_id' => 'integer',
        'mesa_id' => 'integer',
        'delta' => 'integer',
        'is_counted' => 'boolean',
        'occurred_at' => 'datetime',
    ];

    /** Caches estáticos (evitan golpear Schema constantemente) */
    private static ?bool $hasIsCounted = null;
    private static ?string $userHonorColumn = null;

    /* ========================
     * Relaciones
     * ======================*/

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'user_id');
    }

    public function mesa(): BelongsTo
    {
        return $this->belongsTo(Mesa::class, 'mesa_id');
    }

    /* ========================
     * Scopes
     * ======================*/

    /** Sólo eventos contados (si existe la columna) */
    public function scopeCounted($q)
    {
        if (self::hasIsCounted()) {
            $q->where($this->getTable() . '.is_counted', true);
        }
        return $q;
    }

    public function scopeForUser($q, int $userId)
    {
        return $q->where($this->getTable() . '.user_id', $userId);
    }

    /* ========================
     * Aliases reason <-> nota
     * ======================*/

    public function getReasonAttribute(): ?string
    {
        return $this->attributes['reason'] ?? $this->attributes['nota'] ?? null;
    }

    public function setReasonAttribute(?string $value): void
    {
        // Persistimos siempre en 'nota' (más común en tu app)
        $this->attributes['nota'] = $this->truncateNullable($value, self::REASON_MAX);
    }

    public function getNotaAttribute(): ?string
    {
        return $this->attributes['nota'] ?? $this->attributes['reason'] ?? null;
    }

    /* ========================
     * API estática de escritura
     * ======================*/

    /**
     * Crea/actualiza un evento (idempotente por user+slug).
     * Fuerza is_counted=1 si existe la columna.
     */
    public static function award(
        int $userId,
        int $delta,
        string $reason,
        ?int $mesaId = null,
        string $slug = ''
    ): self {
        $slug = $slug !== '' ? $slug : ($reason . ':' . (string) Str::uuid());
        $slug = mb_substr($slug, 0, self::SLUG_MAX);

        $attrs = [
            'user_id' => $userId,
            'slug' => $slug,
        ];

        $data = [
            'mesa_id' => $mesaId,
            'delta' => (int) $delta,
            'nota' => self::truncateStatic($reason, self::REASON_MAX),
            'occurred_at' => now(),
        ];

        if (self::hasIsCounted()) {
            $data['is_counted'] = true;
        }

        // Requiere UNIQUE (user_id, slug) para máxima performance
        return static::updateOrCreate($attrs, $data);
    }

    /**
     * Recalcula y cachea el total de honor en usuarios
     * (usa honor_total > honor si existen).
     */
    public static function recalcTotals(array $userIds): void
    {
        if (empty($userIds))
            return;

        $targetCol = self::resolveUserHonorColumn();
        if ($targetCol === null)
            return;

        $extraWhere = self::hasIsCounted() ? 'AND eh.is_counted = 1' : '';

        // Procesar en chunks para no saturar memoria en compartido
        foreach (array_chunk(array_values(array_unique($userIds)), 1000) as $chunk) {
            DB::table('usuarios')
                ->whereIn('id', $chunk)
                ->update([
                    $targetCol => DB::raw(
                        "(SELECT COALESCE(SUM(delta),0)
                           FROM eventos_honor eh
                          WHERE eh.user_id = usuarios.id {$extraWhere})"
                    ),
                ]);
        }
    }

    /* ========================
     * Internos
     * ======================*/

    private static function hasIsCounted(): bool
    {
        if (self::$hasIsCounted !== null)
            return self::$hasIsCounted;
        return self::$hasIsCounted = Schema::hasColumn('eventos_honor', 'is_counted');
    }

    /** Devuelve 'honor_total' > 'honor' o null si no existe ninguna */
    private static function resolveUserHonorColumn(): ?string
    {
        if (self::$userHonorColumn !== null)
            return self::$userHonorColumn;

        if (Schema::hasColumn('usuarios', 'honor_total')) {
            return self::$userHonorColumn = 'honor_total';
        }
        if (Schema::hasColumn('usuarios', 'honor')) {
            return self::$userHonorColumn = 'honor';
        }
        return self::$userHonorColumn = null;
    }

    /** Trunca string nullable a $max manteniendo null si corresponde. */
    private function truncateNullable(?string $v, int $max): ?string
    {
        if ($v === null)
            return null;
        $v = trim($v);
        return $v === '' ? '' : mb_substr($v, 0, $max);
    }

    /** Versión estática para uso en métodos estáticos. */
    private static function truncateStatic(?string $v, int $max): ?string
    {
        if ($v === null)
            return null;
        $v = trim($v);
        return $v === '' ? '' : mb_substr($v, 0, $max);
    }
}
