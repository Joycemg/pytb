<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Inscripción de un usuario a una mesa.
 * Optimizada para hosting compartido (Hostinger).
 */
final class Inscripcion extends Model
{
    use HasFactory;

    protected $table = 'inscripciones';

    // Actívalo sólo si tu tabla tiene created_at/updated_at
    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'mesa_id',
        'is_waiting',
        'moderated_at',
        'moderated_by',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'mesa_id' => 'integer',
        'moderated_by' => 'integer',
        'is_waiting' => 'boolean',
        'moderated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /* Relaciones */

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'user_id');
    }

    public function mesa(): BelongsTo
    {
        return $this->belongsTo(Mesa::class, 'mesa_id');
    }

    public function moderador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'moderated_by');
    }

    /* Scopes livianos */

    public function scopeConfirmadas($q)
    {
        return $q->where($this->getTable() . '.is_waiting', false);
    }

    public function scopeEnEspera($q)
    {
        return $q->where($this->getTable() . '.is_waiting', true);
    }

    public function scopeNoModeradas($q)
    {
        return $q->whereNull($this->getTable() . '.moderated_at');
    }

    public function scopeModeradas($q)
    {
        return $q->whereNotNull($this->getTable() . '.moderated_at');
    }

    public function scopeParaMesa($q, int $mesaId)
    {
        return $q->where($this->getTable() . '.mesa_id', $mesaId);
    }

    public function scopeParaUsuario($q, int $userId)
    {
        return $q->where($this->getTable() . '.user_id', $userId);
    }

    /* Helpers */

    public function estaModerada(): bool
    {
        return $this->moderated_at !== null;
    }

    public function estaConfirmada(): bool
    {
        return $this->is_waiting === false;
    }

    public function marcarModeradaPor(int $userId): self
    {
        $this->moderated_at = now();
        $this->moderated_by = $userId;
        return $this;
    }
}
