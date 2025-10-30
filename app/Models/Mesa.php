<?php declare(strict_types=1);

// app/Models/Mesa.php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Mesa extends Model
{
    use HasFactory;

    protected $table = 'mesas';

    protected $fillable = [
        'jornada_id',
        'jornada_apartado_id',
        'title',
        'description',
        'created_by',
        'manager_id',
        'capacity',
        'manager_counts_as_player',
        'single_vote',
        'is_open',
        'opens_at',
        'inscripciones_abren_at',
        'closed_at',
        'image_path',
        'image_url',
    ];

    protected $casts = [
        'jornada_id' => 'integer',
        'jornada_apartado_id' => 'integer',
        'created_by' => 'integer',
        'manager_id' => 'integer',
        'capacity' => 'integer',
        'is_open' => 'boolean',
        'manager_counts_as_player' => 'boolean',
        'single_vote' => 'boolean',
        'opens_at' => 'datetime',
        'inscripciones_abren_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    /** Cache interno por request para evitar recuentos repetidos */
    private ?int $capacityLeftCache = null;

    /* ───────── Relaciones ───────── */

    public function creador(): BelongsTo
    {
        // withDefault() evita errores si la FK es null o no existe el usuario
        return $this->belongsTo(Usuario::class, 'created_by')->withDefault();
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'manager_id')->withDefault();
    }

    public function jornada(): BelongsTo
    {
        return $this->belongsTo(Jornada::class, 'jornada_id');
    }

    public function apartado(): BelongsTo
    {
        return $this->belongsTo(JornadaApartado::class, 'jornada_apartado_id');
    }

    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class, 'mesa_id');
    }

    public function inscripcionesConfirmadas(): HasMany
    {
        return $this->hasMany(Inscripcion::class, 'mesa_id')->where('is_waiting', false);
    }

    /* ───────── Scopes ───────── */

    public function scopeAbiertas($q)
    {
        return $q->where($this->getTable() . '.is_open', true);
    }

    public function scopeDeJornada($q, int $jornadaId)
    {
        return $q->where($this->getTable() . '.jornada_id', $jornadaId);
    }

    public function scopeDeApartado($q, ?int $apartadoId)
    {
        $t = $this->getTable();
        return $apartadoId
            ? $q->where($t . '.jornada_apartado_id', $apartadoId)
            : $q->whereNull($t . '.jornada_apartado_id');
    }

    public function scopeParaManager($q, int $userId)
    {
        return $q->where($this->getTable() . '.manager_id', $userId);
    }

    public function scopeVisibles($q)
    {
        $t = $this->getTable();
        return $q->when(
            \Illuminate\Support\Facades\Schema::hasColumn($t, 'is_visible'),
            fn($qq) => $qq->where($t . '.is_visible', true),
            fn($qq) => $qq
        );
    }

    public function scopeBusqueda($q, ?string $term)
    {
        $t = trim((string) $term);
        if ($t === '')
            return $q;

        $t = function_exists('mb_substr') ? mb_substr($t, 0, 80, 'UTF-8') : substr($t, 0, 80);
        $tEsc = addcslashes($t, '%_');
        $like = "%{$tEsc}%";

        $tbl = $this->getTable();

        return $q->where(function ($w) use ($like, $tbl) {
            $w->where($tbl . '.title', 'like', $like)
                ->orWhere($tbl . '.description', 'like', $like);
        });
    }

    public function scopeOrdenReciente($q)
    {
        return $q->orderByDesc($this->getTable() . '.id');
    }

    public function scopeConConfirmadas($q)
    {
        return $q->withCount([
            'inscripciones as confirmadas_count' => function ($qq) {
                $qq->where('is_waiting', false);
            }
        ]);
    }

    public function scopeEfectivamenteAbiertas($q)
    {
        $t = $this->getTable();

        return $q->where($t . '.is_open', true)
            ->where(function ($w) use ($t) {
                $w->whereNull($t . '.opens_at')
                    ->orWhere($t . '.opens_at', '<=', now());
            })
            ->conConfirmadas()
            ->whereRaw(
                '(CASE WHEN ' . $t . '.manager_counts_as_player = 1 ' .
                'THEN ' . $t . '.capacity - 1 ELSE ' . $t . '.capacity END) > COALESCE(confirmadas_count, 0)'
            );
    }

    /**
     * IMPORTANTE:
     * Este select se usa para listar tarjetas. Debe incluir TODAS las columnas
     * que luego se acceden en vistas/helpers/relaciones (FKs y fechas usadas).
     */
    public function scopeSelectCard($q)
    {
        $t = $this->getTable();
        return $q->select([
            $t . '.id',
            $t . '.jornada_id',
            $t . '.jornada_apartado_id',
            $t . '.title',
            $t . '.description',
            $t . '.capacity',
            $t . '.is_open',
            $t . '.image_path',
            $t . '.manager_counts_as_player',
            $t . '.image_url',

            // ← Añadidas para evitar MissingAttributeException
            $t . '.created_by',
            $t . '.manager_id',
            $t . '.opens_at',
            $t . '.inscripciones_abren_at',
            $t . '.closed_at',
        ]);
    }

    /* ───────── Helpers ───────── */

    public function isOpenNow(): bool
    {
        if ($this->is_open !== true) {
            return false;
        }
        return $this->opens_at === null || now()->greaterThanOrEqualTo($this->opens_at);
    }

    public function capacidadEfectiva(): int
    {
        $base = (int) ($this->capacity ?? 0);
        if ($this->manager_counts_as_player) {
            $base -= 1;
        }
        return max(0, $base);
    }

    public function capacityLeft(): int
    {
        if ($this->capacityLeftCache !== null) {
            return $this->capacityLeftCache;
        }

        $cap = $this->capacidadEfectiva();

        // Importante: no usar getAttribute() aquí; en Laravel 12 lanzaría
        // MissingAttributeException si no fue seleccionado con withCount().
        $confirmados = $this->attributes['confirmadas_count'] ?? null;

        if (!is_int($confirmados)) {
            $confirmados = (int) $this->inscripcionesConfirmadas()->toBase()->count();
        }

        return $this->capacityLeftCache = max(0, $cap - $confirmados);
    }

    public function isEffectivelyOpen(): bool
    {
        return $this->isOpenNow() && $this->capacityLeft() > 0;
    }

    protected function openNow(): Attribute
    {
        return Attribute::get(fn(): bool => $this->isOpenNow());
    }

    public function closeIfFull(): bool
    {
        if (!$this->is_open) {
            return false;
        }

        if ($this->capacityLeft() <= 0) {
            $this->is_open = false;
            $this->closed_at ??= now();
            $this->save();
            return true;
        }
        return false;
    }

    /**
     * URL pública de la imagen de la mesa.
     */
    public function getImageSrcAttribute(): ?string
    {
        $path = (string) ($this->image_path ?? '');
        if ($path !== '') {
            $clean = ltrim(preg_replace('#^public/#', '', $path) ?? '', '/');
            return asset('storage/' . $clean);
        }

        $remote = trim((string) ($this->image_url ?? ''));
        if ($remote !== '') {
            $sch = strtolower((string) parse_url($remote, PHP_URL_SCHEME));
            if (in_array($sch, ['http', 'https'], true)) {
                return $remote;
            }
        }

        return null;
    }
}
