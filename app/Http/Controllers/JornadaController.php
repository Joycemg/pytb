<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\MesaCerrada;
use App\Models\Jornada;
use App\Models\EventoHonor;
use App\Models\Mesa;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

final class JornadaController extends Controller
{
    /** Historial por página */
    private const PER_PAGE = 10;

    /** Rate limits (por actor) para acciones críticas */
    private const RL_OPEN_ATTEMPTS = 5;   // por hora
    private const RL_CLOSE_ATTEMPTS = 5;  // por hora
    private const RL_WINDOW_SECONDS = 3600;

    /** Página con estado actual y listado (historial = solo cerradas) */
    public function index(): View
    {
        // Jornada abierta (si existe), sólo columnas necesarias
        $actual = Jornada::abierta()
            ->select(['id', 'estado', 'abierta_at'])
            ->orderByDesc('id')
            ->first();

        // Historial paginado de cerradas (orden estable)
        $hist = Jornada::cerrada()
            ->select(['id', 'estado', 'abierta_at'])
            ->orderByDesc('abierta_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);

        // Número secuencial (1..N) SOLO para IDs de la página actual (ahorra memoria)
        $numeroPorId = [];
        foreach ($hist->items() as $row) {
            $numero = (int) Jornada::cerrada()
                ->where(function ($q) use ($row) {
                    $q->where('abierta_at', '<', $row->abierta_at)
                        ->orWhere(function ($qq) use ($row) {
                            $qq->where('abierta_at', $row->abierta_at)
                                ->where('id', '<=', $row->id);
                        });
                })
                ->count();
            $numeroPorId[$row->id] = $numero; // 1-based
        }

        return view('jornadas.index', compact('actual', 'hist', 'numeroPorId'));
    }

    /** Detalle de jornada (con apartados y mesas) */
    public function show(Jornada $jornada): View
    {
        $this->authorize('view', $jornada);

        // Número secuencial sólo si la jornada está cerrada
        $numero = null;
        $estaCerrada = method_exists($jornada, 'estaCerrada')
            ? $jornada->estaCerrada()
            : ($jornada->estado === Jornada::ESTADO_CERRADA);

        if ($estaCerrada) {
            $numero = (int) Jornada::cerrada()
                ->where(function ($q) use ($jornada) {
                    $q->where('abierta_at', '<', $jornada->abierta_at)
                        ->orWhere(function ($qq) use ($jornada) {
                            $qq->where('abierta_at', $jornada->abierta_at)
                                ->where('id', '<=', $jornada->id);
                        });
                })
                ->count();
        }

        // Cargar mesas + relaciones mínimas + contadores útiles (pendientes)
        $jornada->load([
            'mesas' => function ($q) {
                $q->select([
                    'id',
                    'jornada_id',
                    'jornada_apartado_id',
                    'title',
                    'capacity',
                    'is_open',
                    'closed_at',
                    'manager_id',
                    'created_by'
                ])
                    ->with([
                        'apartado:id,jornada_id,titulo,activo',
                        'manager:id,name',
                        'creador:id,name',
                        'inscripciones' => function ($qq) {
                            $qq->select(['id', 'mesa_id', 'user_id', 'moderated_at', 'moderated_by', 'is_waiting', 'created_at'])
                                ->where('is_waiting', false)
                                ->with(['usuario:id,name', 'moderador:id,name'])
                                ->orderBy('id');
                        },
                    ])
                    ->withCount([
                        'inscripciones as pendientes_count' => function ($w) {
                            $w->where('is_waiting', false)->whereNull('moderated_at');
                        }
                    ]);
            },
            'apartados:id,jornada_id,titulo,orden,activo',
        ]);

        // Precalcular estado asistencia/comportamiento
        $mesaIds = $jornada->mesas->pluck('id')->all();
        $estadoPorMesaUser = [];
        if (!empty($mesaIds)) {
            EventoHonor::query()
                ->whereIn('mesa_id', $mesaIds)
                ->where(function ($w) {
                    $w->where('slug', 'like', 'attendance:mesa:%')
                        ->orWhere('slug', 'like', 'behavior:mesa:%');
                })
                ->get(['mesa_id', 'user_id', 'slug', 'delta'])
                ->each(function ($e) use (&$estadoPorMesaUser) {
                    $key = $e->mesa_id . ':' . $e->user_id;
                    $estadoPorMesaUser[$key] ??= ['asistencia' => null, 'comportamiento' => null];

                    if (str_starts_with($e->slug, 'attendance:')) {
                        $estadoPorMesaUser[$key]['asistencia'] = ((int) ($e->delta ?? 0) >= 0) ? 'presente' : 'noshow';
                    } else {
                        $delta = (int) ($e->delta ?? 0);
                        $estadoPorMesaUser[$key]['comportamiento'] = $delta > 0 ? 'good' : ($delta < 0 ? 'bad' : 'neutral');
                    }
                });
        }

        // Pendientes por mesa (array simple para la vista)
        $pendientesPorMesa = [];
        foreach ($jornada->mesas as $m) {
            $pendientesPorMesa[$m->id] = (int) ($m->getAttribute('pendientes_count') ?? 0);
        }

        // Totales resumidos
        $totalMesas = $jornada->mesas->count();
        $totalJug = (int) DB::table('inscripciones as i')
            ->join('mesas as m', 'm.id', '=', 'i.mesa_id')
            ->where('m.jornada_id', $jornada->getKey())
            ->where('i.is_waiting', false)
            ->count();

        return view('jornadas.show', compact(
            'jornada',
            'numero',
            'totalMesas',
            'totalJug',
            'estadoPorMesaUser',
            'pendientesPorMesa'
        ));
    }

    /** Abrir nueva jornada */
    public function abrir(Request $r): RedirectResponse
    {
        $this->authorize('open', Jornada::class);

        $rlKey = sprintf('rl:jornada:abrir:%d', (int) $r->user()->id);
        if (RateLimiter::tooManyAttempts($rlKey, self::RL_OPEN_ATTEMPTS)) {
            return back()->with('error', 'Demasiados intentos de apertura. Probá más tarde.');
        }

        // Bloqueos lógicos
        $ultimaCerrada = Jornada::cerrada()->select(['id'])->orderByDesc('id')->first();
        if ($ultimaCerrada && !$ultimaCerrada->moderacionCompleta()) {
            return back()->with('error', 'No se puede abrir una nueva jornada: hay una jornada cerrada con moderación incompleta.');
        }
        if (Jornada::abierta()->exists()) {
            return back()->with('error', 'Ya hay una jornada abierta.');
        }

        try {
            DB::transaction(function () use ($r) {
                if (Jornada::abierta()->lockForUpdate()->exists()) {
                    throw new \RuntimeException('Ya hay una jornada abierta.');
                }

                Jornada::create([
                    'titulo' => now()->timezone(config('app.timezone', 'UTC'))->format('d/m/Y'),
                    'estado' => Jornada::ESTADO_ABIERTA,
                    'abierta_at' => now(),
                    'abierta_por' => $r->user()->id,
                ]);
            }, 3);
            RateLimiter::hit($rlKey, self::RL_WINDOW_SECONDS);
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo abrir la jornada. Intentá nuevamente.');
        }

        return back()->with('ok', 'Jornada abierta');
    }

    /** Cerrar jornada actual (con verificación de contraseña) */
    public function cerrar(Request $r, Jornada $jornada): RedirectResponse
    {
        $this->authorize('close', $jornada);

        // Acepta admin_password | password | __admin_pwd_checked
        $pwd = (string) (
            $r->input('admin_password', '') ?:
            $r->input('password', '') ?:
            $r->input('__admin_pwd_checked', '')
        );
        // Merge a 'password' para usar current_password:web
        $r->merge(['password' => $pwd]);

        $r->validate([
            'password' => ['required', 'current_password:web'],
        ], [
            'password.required' => 'Falta confirmar con tu contraseña.',
            'password.current_password' => 'La contraseña no es correcta.',
        ]);

        $estaAbierta = method_exists($jornada, 'estaAbierta')
            ? $jornada->estaAbierta()
            : ($jornada->estado === Jornada::ESTADO_ABIERTA);

        if (!$estaAbierta) {
            return back()->with('error', 'La jornada ya está cerrada.');
        }

        $rlKey = sprintf('rl:jornada:cerrar:%d', (int) $r->user()->id);
        if (RateLimiter::tooManyAttempts($rlKey, self::RL_CLOSE_ATTEMPTS)) {
            return back()->with('error', 'Demasiados intentos de cierre. Probá más tarde.');
        }

        try {
            $mesasAfectadas = [];

            DB::transaction(function () use ($jornada, $r, &$mesasAfectadas) {
                // IDs de mesas que estaban abiertas
                $mesasAfectadas = DB::table('mesas')
                    ->where('jornada_id', $jornada->id)
                    ->where('is_open', true)
                    ->pluck('id')
                    ->all();

                // Cerrar todas las mesas abiertas (una sola query)
                DB::table('mesas')
                    ->where('jornada_id', $jornada->id)
                    ->where('is_open', true)
                    ->update([
                        'is_open' => false,
                        'closed_at' => now(),
                    ]);

                // Marcar jornada cerrada
                $jornada->update([
                    'estado' => Jornada::ESTADO_CERRADA,
                    'cerrada_at' => now(),
                    'cerrada_por' => $r->user()->id,
                ]);
            }, 5);

            // Eventos post-commit
            if (!empty($mesasAfectadas)) {
                DB::afterCommit(function () use ($mesasAfectadas) {
                    foreach (array_chunk($mesasAfectadas, 200) as $chunk) {
                        Mesa::query()
                            ->select('id', 'jornada_id', 'title', 'manager_id', 'capacity', 'closed_at', 'is_open')
                            ->withCount(['inscripciones as inscripciones_count' => fn($q) => $q->where('is_waiting', false)])
                            ->whereIn('id', $chunk)
                            ->get()
                            ->each(function ($m) {
                                event(new MesaCerrada($m, firstClose: true));
                            });
                    }
                });
            }

            RateLimiter::hit($rlKey, self::RL_WINDOW_SECONDS);
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo cerrar la jornada. Intentá nuevamente.');
        }

        return back()->with('ok', 'Jornada cerrada. Podés moderar asistencia y comportamiento desde el historial.');
    }

    /** API simple para saber si falta moderar */
    public function estadoModeracion(Jornada $jornada): JsonResponse
    {
        $this->authorize('view', $jornada);

        return $this->okEtagged(request(), [
            'completa' => (bool) $jornada->moderacionCompleta(),
        ], 30);
    }
}
