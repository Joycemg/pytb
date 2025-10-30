<?php declare(strict_types=1);

// app/Models/JornadaApartado.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Apartado dentro de una Jornada.
 *
 * Pensado para hosting compartido:
 * - Scopes baratos (activos/ordenado/por jornada).
 * - Helpers con exists()/count() para evitar hidratar colecciones.
 */
final class JornadaApartado extends Model
{
    protected $table = 'jornada_apartados';

    // Si no manejás created_at/updated_at, comentá la línea de abajo
    public $timestamps = false;

    protected $fillable = [
        'jornada_id',
        'titulo',
        'orden',
        'activo',
    ];

    protected $casts = [
        'jornada_id' => 'integer',
        'orden' => 'integer',
        'activo' => 'boolean',
    ];

    /* Relaciones */

    public function jornada(): BelongsTo
    {
        return $this->belongsTo(Jornada::class, 'jornada_id');
    }

    public function mesas(): HasMany
    {
        return $this->hasMany(Mesa::class, 'jornada_apartado_id');
    }

    /* Scopes */

    /** Sólo apartados activos */
    public function scopeActivos($q)
    {
        return $q->where($this->getTable() . '.activo', true);
    }

    /** Orden consistente por (orden, id) */
    public function scopeOrdenado($q)
    {
        $t = $this->getTable();
        return $q->orderBy($t . '.orden')->orderBy($t . '.id');
    }

    /** Filtra por id de jornada */
    public function scopeDeJornada($q, int $jornadaId)
    {
        return $q->where($this->getTable() . '.jornada_id', $jornadaId);
    }

    /* Helpers */

    /** ¿Tiene mesas asociadas? */
    public function tieneMesas(): bool
    {
        return $this->mesas()->exists();
    }

    /** Cantidad de mesas abiertas del apartado (para badges en UI) */
    public function mesasAbiertasCount(): int
    {
        return (int) $this->mesas()->where('is_open', true)->count();
    }
}
