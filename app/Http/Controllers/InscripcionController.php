<?php declare(strict_types=1);

// app/Http/Controllers/InscripcionController.php

namespace App\Http\Controllers;

use App\Events\MesaCerrada;
use App\Models\Inscripcion;
use App\Models\Mesa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Carbon\Carbon;

final class InscripcionController extends Controller
{
    /** Reintentos ante deadlocks/timeouts en hosting compartido */
    private const TX_RETRIES = 3;

    /** Mensajes estándar */
    private const MSG_LLENA = 'La mesa se llenó.';
    private const MSG_REINTENTA = 'No se pudo procesar la inscripción. Intentá nuevamente.';
    private const MSG_CERRADA = 'La mesa está cerrada.';
    private const MSG_ANTES_HORARIO = 'Las inscripciones para esta mesa se habilitan el %s (%s).';

    /**
     * Inscribirse (sin lista de espera).
     * Reglas:
     * - Si sos manager de alguna mesa de la MISMA jornada, NO podés inscribirte.
     * - La capacidad efectiva descuenta 1 si "manager_counts_as_player" está activo.
     * - Voto único por apartado dentro de la jornada (si la mesa no tiene apartado, es el apartado por defecto).
     * - Al llenarse, cierra automáticamente la mesa y emite evento.
     * - Respeta horario de apertura (opens_at).
     */
    public function store(Request $r, Mesa $mesa): RedirectResponse
    {
        $this->authorize('signup', $mesa);

        $user = $r->user();
        $mesa->loadMissing('jornada:id', 'apartado:id,jornada_id');

        // === (0.a) Horario de apertura: bloquea si todavía no es la hora (server time) ===
        $tz = config('app.display_timezone', config('app.timezone', 'America/Argentina/La_Rioja'));

        $openAt = $mesa->opens_at ? Carbon::parse($mesa->opens_at, $tz) : null;

        if ($openAt && Carbon::now($tz)->lt($openAt)) {
            return back()
                ->with('error', sprintf(self::MSG_ANTES_HORARIO, $openAt->isoFormat('DD/MM/YYYY HH:mm'), $tz))
                ->withInput();
        }

        // (0.b) Si el usuario es manager en esta jornada → no puede inscribirse
        $esManagerEnJornada = Mesa::query()
            ->where('jornada_id', $mesa->jornada_id)
            ->where('manager_id', $user->id)
            ->exists();

        if ($esManagerEnJornada) {
            return back()
                ->with('error', 'Sos manager en esta jornada; los managers no se inscriben en mesas.')
                ->withInput();
        }

        // (0.c) Si la mesa ya está cerrada, cortamos temprano
        if (!(bool) $mesa->is_open) {
            return back()->with('error', self::MSG_CERRADA)->withInput();
        }

        // (1) Voto único **por apartado** dentro de la misma jornada (chequeo previo “rápido”)
        $jornadaId = (int) $mesa->jornada_id;
        $apartadoId = (int) ($mesa->jornada_apartado_id ?? 0); // 0 = sin apartado

        $yaEnMismoApartado = Inscripcion::query()
            ->where('user_id', $user->id)
            ->where('is_waiting', false)
            ->whereHas('mesa', function ($mq) use ($jornadaId, $apartadoId, $mesa) {
                $mq->where('jornada_id', $jornadaId)
                    ->when(
                        $apartadoId > 0,
                        fn($q) => $q->where('jornada_apartado_id', $apartadoId),
                        fn($q) => $q->whereNull('jornada_apartado_id')
                    )
                    ->where('id', '!=', $mesa->id);
            })
            ->exists();

        if ($yaEnMismoApartado) {
            return back()
                ->with('error', 'Ya estás anotado en una mesa de este apartado de la jornada.')
                ->withInput();
        }

        // (2) Ya estaba inscripto en esta mesa (idempotencia amable)
        $ya = Inscripcion::where('mesa_id', $mesa->id)
            ->where('user_id', $user->id)
            ->first();

        if ($ya) {
            return redirect()->route('mesas.show', $mesa)->with('ok', 'Ya estabas inscripto en esta mesa.');
        }

        // (3) Crear inscripción y cerrar si llena — con reintentos para evitar deadlocks
        $userId = (int) $user->id;
        $error = null;
        $attempts = 0;

        while ($attempts < self::TX_RETRIES) {
            $attempts++;
            try {
                DB::transaction(function () use ($mesa, $userId, $jornadaId, $apartadoId, $tz, &$error) {
                    /** @var Mesa|null $m */
                    $m = Mesa::query()
                        ->select(['id', 'jornada_id', 'capacity', 'is_open', 'closed_at', 'manager_counts_as_player', 'jornada_apartado_id', 'opens_at'])
                        ->whereKey($mesa->id)
                        ->lockForUpdate()
                        ->first();

                    if (!$m) {
                        $error = 'La mesa no existe.';
                        return;
                    }

                    // Revalidar horario bajo lock (evita carreras con cambios)
                    $openAtTx = $m->opens_at ? Carbon::parse($m->opens_at, $tz) : null;

                    // Si ya pasó la hora y aún está cerrada, abrir aquí mismo
                    if ($openAtTx && Carbon::now($tz)->greaterThanOrEqualTo($openAtTx) && !$m->is_open) {
                        $m->is_open = true;
                        $m->closed_at = null;
                        $m->save();
                    }

                    // Si es antes de hora, bloquear
                    if ($openAtTx && Carbon::now($tz)->lt($openAtTx)) {
                        $error = sprintf(self::MSG_ANTES_HORARIO, $openAtTx->isoFormat('DD/MM/YYYY HH:mm'), $tz);
                        return;
                    }

                    if (!(bool) $m->is_open) {
                        $error = self::MSG_CERRADA;
                        return;
                    }

                    // Revalidar voto único por apartado BAJO LOCK para evitar carrera
                    $yaEnMismoApartadoTx = Inscripcion::query()
                        ->join('mesas as mm', 'mm.id', '=', 'inscripciones.mesa_id')
                        ->where('inscripciones.user_id', $userId)
                        ->where('inscripciones.is_waiting', false)
                        ->where('mm.jornada_id', $jornadaId)
                        ->when(
                            $apartadoId > 0,
                            fn($q) => $q->where('mm.jornada_apartado_id', $apartadoId),
                            fn($q) => $q->whereNull('mm.jornada_apartado_id')
                        )
                        ->where('mm.id', '!=', $m->id)
                        ->lockForUpdate()
                        ->exists();

                    if ($yaEnMismoApartadoTx) {
                        $error = 'Ya estás anotado en una mesa de este apartado de la jornada.';
                        return;
                    }

                    // Capacidad efectiva
                    $capBase = $this->capacidadEfectiva($m);
                    if ($capBase <= 0) {
                        $error = self::MSG_LLENA;
                        return;
                    }

                    // ¿Cupo disponible?
                    $confirmados = (int) $m->inscripciones()
                        ->where('is_waiting', false)
                        ->toBase()
                        ->count();

                    if ($confirmados >= $capBase) {
                        $error = self::MSG_LLENA;
                        return;
                    }

                    // Insert idempotente (requiere UNIQUE (mesa_id,user_id) en DB para blindaje total)
                    try {
                        Inscripcion::firstOrCreate(
                            ['user_id' => $userId, 'mesa_id' => $m->id],
                            ['is_waiting' => false]
                        );
                    } catch (QueryException $qe) {
                        // Choque de UNIQUE por carrera → tratar como "ya estabas"
                        return;
                    }

                    // Recontar y cerrar si llenó
                    $confirmados = (int) $m->inscripciones()
                        ->where('is_waiting', false)
                        ->toBase()
                        ->count();

                    if ($confirmados >= $capBase && $m->is_open) {
                        $m->is_open = false;
                        $m->closed_at ??= now();
                        $m->save();

                        // Emitir evento con snapshot
                        event(new MesaCerrada($m, firstClose: empty($m->getOriginal('closed_at'))));
                    }
                }, 5);
                break; // OK
            } catch (\Throwable $e) {
                if ($attempts >= self::TX_RETRIES) {
                    $error = self::MSG_REINTENTA;
                } else {
                    // Backoff suave + jitter (hosting compartido)
                    usleep(random_int(120_000, 260_000));
                    continue;
                }
            }
        }

        if ($error) {
            return back()->with('error', $error)->withInput();
        }

        return redirect()->route('mesas.show', $mesa)->with('ok', 'Inscripción confirmada');
    }

    /** Quitar inscripción (reabre si queda cupo) */
    public function destroy(Request $r, Mesa $mesa): RedirectResponse
    {
        $userId = (int) $r->user()->id;
        $attempts = 0;

        while ($attempts < self::TX_RETRIES) {
            $attempts++;
            try {
                DB::transaction(function () use ($mesa, $userId) {
                    /** @var Mesa|null $m */
                    $m = Mesa::query()
                        ->select(['id', 'capacity', 'is_open', 'closed_at', 'manager_counts_as_player'])
                        ->whereKey($mesa->id)
                        ->lockForUpdate()
                        ->first();

                    if (!$m) {
                        return; // mesa borrada → nada que hacer
                    }

                    // Borrado directo (idempotente; evita SELECT previo)
                    Inscripcion::where('mesa_id', $m->id)
                        ->where('user_id', $userId)
                        ->limit(1)
                        ->delete();

                    $capBase = $this->capacidadEfectiva($m);
                    $confirmados = (int) $m->inscripciones()
                        ->where('is_waiting', false)
                        ->toBase()
                        ->count();

                    if ($confirmados < $capBase && !$m->is_open) {
                        $m->is_open = true;
                        $m->save();
                        // opcional: emitir evento "reapertura"
                    }
                }, 5);
                break;
            } catch (\Throwable $e) {
                if ($attempts >= self::TX_RETRIES) {
                    return back()->with('error', 'No se pudo quitar tu inscripción. Intentá nuevamente.');
                }
                usleep(random_int(120_000, 260_000));
            }
        }

        return redirect()->route('mesas.show', $mesa)->with('ok', 'Quitaste tu voto');
    }

    /* ===================== helpers privados ===================== */

    /** Capacidad efectiva segura (usa helper del modelo si existe) */
    private function capacidadEfectiva(Mesa $m): int
    {
        if (method_exists($m, 'capacidadEfectiva')) {
            /** @phpstan-ignore-next-line */
            return max(0, (int) $m->capacidadEfectiva());
        }
        return max(0, (int) $m->capacity - ((bool) $m->manager_counts_as_player ? 1 : 0));
    }
}
