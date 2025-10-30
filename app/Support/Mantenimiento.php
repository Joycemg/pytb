<?php declare(strict_types=1);

namespace App\Support;

use App\Events\MesaCerrada;
use App\Models\EventoHonor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class Mantenimiento
{
    /** Tamaños de lote pensados para hosting compartido */
    private const CHUNK_USERS = 500;
    private const CHUNK_MESAS = 200;

    /**
     * Penalización diaria por inactividad (idempotente por día).
     */
    public static function decaerHonor(): void
    {
        $now = now();
        $since = $now->copy()->subDays(30);
        $slugDay = $now->format('Ymd');

        $recentSub = DB::table('eventos_honor as eh')
            ->select('eh.user_id')
            ->where('eh.occurred_at', '>=', $since)
            ->groupBy('eh.user_id');

        DB::table('usuarios')
            ->leftJoinSub($recentSub, 'r', fn($j) => $j->on('r.user_id', '=', 'usuarios.id'))
            ->whereNull('r.user_id')
            ->orderBy('usuarios.id')
            ->select('usuarios.id')
            // chunkById: usar la columna simple 'id' (Laravel la aplica a la tabla base)
            ->chunkById(self::CHUNK_USERS, function ($rows) use ($slugDay) {
                foreach ($rows as $row) {
                    try {
                        EventoHonor::award(
                            (int) $row->id,
                            -1,
                            'inactividad',
                            mesaId: null,
                            slug: 'inact:' . $slugDay
                        );
                    } catch (\Throwable $e) {
                        try {
                            Log::warning('decaerHonor: fila fallida', ['user_id' => (int) $row->id, 'err' => $e->getMessage()]);
                        } catch (\Throwable) {
                        }
                    }
                }
                usleep(80_000); // 80ms
            }, 'id');
    }

    /**
     * Cierre automático de mesas abiertas hace > 8h.
     */
    public static function cerrarMesasAntiguas(): void
    {
        $limit = now()->subHours(8);
        $now = now(); // mantener una marca consistente para closed_at

        DB::table('mesas')
            ->where('is_open', true)
            ->whereNotNull('opens_at')
            ->where('opens_at', '<', $limit)
            ->whereNull('closed_at')
            ->orderBy('id')
            ->select('id')
            ->chunkById(self::CHUNK_MESAS, function ($rows) use ($now) {
                foreach ($rows as $row) {
                    $mesaId = (int) $row->id;

                    try {
                        $updated = DB::table('mesas')
                            ->where('id', $mesaId)
                            ->where('is_open', true)
                            ->whereNull('closed_at')
                            ->update([
                                'is_open' => false,
                                'closed_at' => $now,
                            ]);

                        if ($updated > 0) {
                            $mesa = \App\Models\Mesa::query()
                                ->select('id', 'jornada_id', 'title', 'manager_id', 'capacity', 'closed_at', 'is_open')
                                ->withCount([
                                    'inscripciones as inscripciones_count' => fn($q) => $q->where('is_waiting', false),
                                ])
                                ->find($mesaId);

                            if ($mesa) {
                                try {
                                    event(new MesaCerrada($mesa, firstClose: true));
                                } catch (\Throwable $e) {
                                    // evento falló: no frenar el mantenimiento
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        try {
                            Log::warning('cerrarMesasAntiguas: fallo al cerrar mesa', ['mesa_id' => $mesaId, 'err' => $e->getMessage()]);
                        } catch (\Throwable) {
                        }
                    }
                }
                usleep(60_000); // 60ms
            }, 'id');
    }
}
