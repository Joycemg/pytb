<?php declare(strict_types=1);

namespace App\Events;

use App\Models\Mesa;
use Carbon\CarbonImmutable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use JsonSerializable;

/**
 * Evento inmutable de “mesa cerrada”, optimizado para hosting compartido.
 * - Payload mínimo y primitivo para listeners en cola (sin reconsultar DB).
 * - Compatible con withCount('inscripciones as inscripciones_count').
 * - Fallback barato con toBase()->count() si no se trajo el withCount.
 */
final class MesaCerrada implements JsonSerializable
{
    use SerializesModels;

    /** Modelo (SerializesModels lo reduce a ID si el listener va a cola) */
    public readonly Mesa $mesa;

    /** Meta/idempotencia/diagnóstico */
    public readonly string $eventId;
    public readonly string $slug;
    public readonly bool $firstClose;

    /** Primitivos útiles para listeners/logs/métricas */
    public readonly int $mesaId;
    public readonly int $jornadaId;
    public readonly ?int $managerId;
    public readonly int $inscripcionesCount;

    /**
     * Snapshot autocontenido (todo en primitivos/strings):
     * id, jornada_id, title, capacity, is_open, closed_at_iso, insc_count, first_close, event_id, slug, tz.
     *
     * @var array<string,int|string|bool|null>
     */
    public readonly array $snapshot;

    public function __construct(Mesa $mesa, bool $firstClose = false)
    {
        $this->mesa = $mesa;
        $this->firstClose = $firstClose;
        $this->eventId = (string) Str::uuid();

        // ===== 1) Conteo de inscripciones (prefiere withCount si vino en el modelo) =====
        $countAttr = $mesa->getAttribute('inscripciones_count');
        if (is_numeric($countAttr)) {
            $count = (int) $countAttr;
        } else {
            // Fallback liviano; si querés sólo confirmadas, agrega where('is_waiting', false)
            $count = (int) $mesa->inscripciones()->toBase()->count();
        }
        $this->inscripcionesCount = $count;

        // ===== 2) Identificadores primitivos =====
        $this->mesaId = (int) $mesa->getKey();
        $this->jornadaId = (int) ($mesa->getAttribute('jornada_id') ?? 0);
        $this->managerId = $mesa->getAttribute('manager_id') !== null
            ? (int) $mesa->getAttribute('manager_id')
            : null;

        // ===== 3) TZ y slug estable por día =====
        $tz = (string) config('app.display_timezone', config('app.timezone', 'UTC'));
        $when = CarbonImmutable::now($tz)->format('Ymd');
        $this->slug = sprintf('close:%d:%s', $this->mesaId, $when);

        // ===== 4) Snapshot autocontenido =====
        $closedAt = $mesa->getAttribute('closed_at');
        $closedIso = $closedAt
            ? ($closedAt instanceof \DateTimeInterface
                ? $closedAt->format(\DateTimeInterface::ATOM)
                : (string) $closedAt)
            : null;

        $this->snapshot = [
            'id' => $this->mesaId,
            'jornada_id' => $this->jornadaId,
            'manager_id' => $this->managerId,
            'title' => (string) ($mesa->getAttribute('title') ?? ''),
            'capacity' => (int) ($mesa->getAttribute('capacity') ?? 0),
            'is_open' => (bool) ($mesa->getAttribute('is_open') ?? false),
            'closed_at_iso' => $closedIso,
            'insc_count' => $this->inscripcionesCount,
            'first_close' => $this->firstClose,
            'event_id' => $this->eventId,
            'slug' => $this->slug,
            'tz' => $tz,
        ];
    }

    /** JsonSerializable: útil para loguear rápido sin armar arrays a mano */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** Conveniencia para logs/tests */
    public function toArray(): array
    {
        return $this->snapshot;
    }
}
