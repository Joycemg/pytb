<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Inscripcion;
use App\Models\Mesa;
use App\Models\EventoHonor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\RateLimiter;

final class ModeracionController extends Controller
{
    private const PTS_ASISTENCIA = 10;
    private const PTS_NOSHOW = -20;
    private const PTS_GOOD = 5;
    private const PTS_BAD = -10;
    private const PTS_NEUTRAL = 0;

    private const TX_RETRIES = 3;
    private const UPSERT_CHUNK = 200;
    private const BACKOFF_US = 200_000; // 200ms

    /** Rate limits (por actor+mesa y global por mesa) */
    private const RL_ACTOR_ATTEMPTS = 12;   // por hora
    private const RL_MESA_ATTEMPTS = 30;   // por hora
    private const RL_WINDOW_SECONDS = 3600;

    public function confirmarMesa(Request $r, Mesa $mesa): RedirectResponse
    {
        $user = $r->user();
        $tieneRol = method_exists($user, 'hasAnyRole') ? $user->hasAnyRole(['admin', 'moderator']) : false;

        // Permisos: admin/mod o manager de la mesa
        if (!($tieneRol || (int) $user->id === (int) ($mesa->manager_id ?? 0))) {
            abort(403);
        }

        // (Opcional pero recomendado) Evitar moderar mesas abiertas
        if ((bool) $mesa->is_open === true) {
            return back()->with('error', 'La mesa está abierta; cerrala antes de moderar.')->withInput();
        }

        // Rate-limit suave
        $kActor = sprintf('rl:mod:actor:%d:%d', (int) $user->id, (int) $mesa->id);
        $kMesa = sprintf('rl:mod:mesa:%d', (int) $mesa->id);
        if (
            RateLimiter::tooManyAttempts($kActor, self::RL_ACTOR_ATTEMPTS)
            || RateLimiter::tooManyAttempts($kMesa, self::RL_MESA_ATTEMPTS)
        ) {
            return back()->with('error', 'Demasiados intentos de moderación. Probá más tarde.');
        }

        $data = $r->validate([
            'password' => ['required', 'current_password:web'],
            'asistencia' => ['nullable', 'array'],
            'asistencia.*' => ['in:1'],
            'comportamiento' => ['nullable', 'array'],
            'comportamiento.*' => ['in:neutral,good,bad'],
        ]);

        $asistencia = (array) ($data['asistencia'] ?? []);
        $comportamiento = (array) ($data['comportamiento'] ?? []);

        // Sólo inscripciones sin moderar (consulta liviana)
        $inscripciones = $mesa->inscripciones()
            ->select(['id', 'mesa_id', 'user_id', 'moderated_at', 'moderated_by', 'is_waiting'])
            ->where('is_waiting', false)
            ->whereNull('moderated_at')
            ->get();

        if ($inscripciones->isEmpty()) {
            return back()->with('ok', 'No había nada para confirmar.');
        }

        $mapComp = [
            'neutral' => self::PTS_NEUTRAL,
            'good' => self::PTS_GOOD,
            'bad' => self::PTS_BAD,
        ];

        $now = now();

        // Cacheo estático de esquema
        static $hasIsCountedCache = null, $reasonColCache = null;
        $hasIsCounted = $hasIsCountedCache ??= Schema::hasColumn('eventos_honor', 'is_counted');
        $reasonCol = $reasonColCache ??= (Schema::hasColumn('eventos_honor', 'reason') ? 'reason' : 'nota');

        $attempts = 0;
        while ($attempts < self::TX_RETRIES) {
            $attempts++;
            try {
                DB::transaction(function () use ($inscripciones, $asistencia, $comportamiento, $mapComp, $user, $now, $hasIsCounted, $reasonCol, $mesa) {
                    $rows = [];

                    foreach ($inscripciones as $i) {
                        $uid = (int) $i->user_id;
                        $present = array_key_exists((string) $uid, $asistencia) && (int) $asistencia[$uid] === 1;
                        $compKey = $present ? (string) ($comportamiento[$uid] ?? 'neutral') : 'neutral';
                        $compPts = $mapComp[$compKey] ?? self::PTS_NEUTRAL;

                        // Asistencia (idempotente por user_id+slug)
                        $attendance = [
                            'user_id' => $uid,
                            'mesa_id' => (int) $i->mesa_id,
                            'slug' => "attendance:mesa:{$i->mesa_id}:user:{$uid}",
                            'delta' => $present ? self::PTS_ASISTENCIA : self::PTS_NOSHOW,
                            $reasonCol => $present ? 'Asistencia: presente' : 'Asistencia: no show',
                            'occurred_at' => $now,
                        ];
                        if ($hasIsCounted) {
                            $attendance['is_counted'] = true;
                        }
                        $rows[] = $attendance;

                        // Comportamiento (sólo si estuvo presente)
                        if ($present) {
                            $behavior = [
                                'user_id' => $uid,
                                'mesa_id' => (int) $i->mesa_id,
                                'slug' => "behavior:mesa:{$i->mesa_id}:user:{$uid}",
                                'delta' => $compPts,
                                $reasonCol => "Comportamiento: {$compKey}",
                                'occurred_at' => $now,
                            ];
                            if ($hasIsCounted) {
                                $behavior['is_counted'] = true;
                            }
                            $rows[] = $behavior;
                        }
                    }

                    if (!empty($rows)) {
                        // Upsert masivo (por chunks) con fallback por filas
                        $colsToUpdate = $hasIsCounted
                            ? ['mesa_id', 'delta', $reasonCol, 'occurred_at', 'is_counted']
                            : ['mesa_id', 'delta', $reasonCol, 'occurred_at'];

                        $upsertOk = true;
                        try {
                            foreach (array_chunk($rows, self::UPSERT_CHUNK) as $chunk) {
                                EventoHonor::upsert(
                                    $chunk,
                                    ['user_id', 'slug'],
                                    $colsToUpdate
                                );
                            }
                        } catch (\Throwable $e) {
                            $upsertOk = false;
                            Log::warning('Moderación: fallo upsert masivo, fallback por filas', [
                                'mesa_id' => $mesa->id,
                                'err' => $e->getMessage(),
                            ]);
                        }

                        if (!$upsertOk) {
                            foreach ($rows as $row) {
                                EventoHonor::updateOrCreate(
                                    ['user_id' => $row['user_id'], 'slug' => $row['slug']],
                                    $row + [] // ya incluye $reasonCol y demás
                                );
                            }
                        }
                    }

                    // Marcar inscripciones como moderadas (bulk)
                    $ids = $inscripciones->pluck('id')->all();
                    if ($ids) {
                        Inscripcion::whereIn('id', $ids)->update([
                            'moderated_at' => $now,
                            'moderated_by' => $user->id,
                        ]);
                    }
                }, 10);

                // RL hits tras éxito
                RateLimiter::hit($kActor, self::RL_WINDOW_SECONDS);
                RateLimiter::hit($kMesa, self::RL_WINDOW_SECONDS);
                break; // OK
            } catch (\Throwable $e) {
                if ($attempts >= self::TX_RETRIES) {
                    Log::error('Moderación: fallo definitivo', [
                        'mesa_id' => $mesa->id,
                        'err' => $e->getMessage(),
                    ]);
                    return back()->with('error', 'No se pudo confirmar la moderación. Intentá nuevamente.');
                }
                usleep(self::BACKOFF_US); // backoff suave para no pelear CPU/IO
            }
        }

        // Recontar honor en usuarios (si hay columna de cache) — post-commit para no bloquear
        $userIds = $inscripciones->pluck('user_id')->unique()->values()->all();
        if ($userIds) {
            DB::afterCommit(function () use ($userIds, $hasIsCounted) {
                try {
                    $targetCol = Schema::hasColumn('usuarios', 'honor_total')
                        ? 'honor_total'
                        : (Schema::hasColumn('usuarios', 'honor') ? 'honor' : null);

                    if ($targetCol) {
                        $extraWhere = $hasIsCounted ? 'AND eh.is_counted = 1' : '';
                        DB::table('usuarios')
                            ->whereIn('id', $userIds)
                            ->update([
                                $targetCol => DB::raw(
                                    "(SELECT COALESCE(SUM(delta),0)
                                       FROM eventos_honor eh
                                       WHERE eh.user_id = usuarios.id {$extraWhere})"
                                ),
                            ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Moderación: recálculo de honor falló', ['err' => $e->getMessage()]);
                }
            });
        }

        return back()->with('ok', 'Moderación confirmada.');
    }
}
