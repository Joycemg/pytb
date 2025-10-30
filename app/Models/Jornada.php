<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Modelo de Jornada
 *
 * Optimizado para hosting compartido (Hostinger):
 * - Scopes reutilizables.
 * - EXISTS/COUNT baratos.
 * - Casts estrictos y helpers sin hidratar colecciones.
 */
final class Jornada extends Model
{
    protected $table = 'jornadas';

    // Si tu tabla NO tiene created_at/updated_at, esto evita writes inexistentes
    public $timestamps = false;

    public const ESTADO_ABIERTA = 'abierta';
    public const ESTADO_CERRADA = 'cerrada';

    protected $fillable = [
        'titulo',
        'estado',
        'abierta_at',
        'cerrada_at',
        'abierta_por',
        'cerrada_por',
    ];

    protected $casts = [
        'abierta_at' => 'datetime',
        'cerrada_at' => 'datetime',
        'abierta_por' => 'integer',
        'cerrada_por' => 'integer',
    ];

    /* Relaciones */

    public function mesas(): HasMany
    {
        return $this->hasMany(Mesa::class, 'jornada_id');
    }

    public function apartados(): HasMany
    {
        return $this->hasMany(JornadaApartado::class, 'jornada_id')
            ->orderBy('orden')->orderBy('id');
    }

    /* Scopes */

    public function scopeAbierta($q)
    {
        return $q->where($this->getTable() . '.estado', self::ESTADO_ABIERTA);
    }

    public function scopeCerrada($q)
    {
        return $q->where($this->getTable() . '.estado', self::ESTADO_CERRADA);
    }

    /** Última jornada abierta (orden por id desc) */
    public function scopeActual($q)
    {
        return $q->orderByDesc($this->getTable() . '.id');
    }

    /* Helpers */

    public function estaAbierta(): bool
    {
        return $this->estado === self::ESTADO_ABIERTA;
    }

    public function estaCerrada(): bool
    {
        return $this->estado === self::ESTADO_CERRADA;
    }

    /**
     * ¿Toda la moderación de la jornada fue completada?
     * EXISTS con JOIN (barato).
     */
    public function moderacionCompleta(): bool
    {
        $jid = (int) $this->getKey();

        $hayPendientes = DB::table('inscripciones as i')
            ->join('mesas as m', 'm.id', '=', 'i.mesa_id')
            ->where('m.jornada_id', $jid)
            ->where('i.is_waiting', false)
            ->whereNull('i.moderated_at')
            ->limit(1)
            ->exists();

        return !$hayPendientes;
    }

    /** Cantidad de inscripciones sin moderar en la jornada. */
    public function pendientesModeracion(): int
    {
        $jid = (int) $this->getKey();

        return (int) DB::table('inscripciones as i')
            ->join('mesas as m', 'm.id', '=', 'i.mesa_id')
            ->where('m.jornada_id', $jid)
            ->where('i.is_waiting', false)
            ->whereNull('i.moderated_at')
            ->count();
    }

    /** ¿La jornada posee apartados activos? */
    public function tieneApartados(): bool
    {
        return $this->apartados()->where('activo', true)->exists();
    }

    /** ¿Hay alguna mesa abierta bajo esta jornada? */
    public function tieneMesasAbiertas(): bool
    {
        return $this->mesas()->where('is_open', true)->exists();
    }
}
