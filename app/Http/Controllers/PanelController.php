<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

final class PanelController extends Controller
{
    /** Límites razonables para el panel */
    private const INSCRIPCIONES_LIMIT = 50;
    private const EVENTOS_LIMIT = 20;
    /** TTL para cachear detección de columna de texto en eventos_honor (reduce costo en compartido) */
    private const SCHEMA_TTL = 86400; // 24h

    /**
     * Dashboard del usuario autenticado.
     * - Sólo columnas necesarias (usuario + relaciones mínimas).
     * - Relaciones acotadas y ordenadas (evita N+1 y picos de CPU/RAM).
     * - Límite razonable de resultados y orden que muestra primero confirmadas.
     */
    public function show(Request $r): View|RedirectResponse
    {
        // Sin sesión → redirige sin tocar DB
        $auth = $r->user();
        if (!$auth) {
            return redirect()
                ->route('auth.login')
                ->with('error', 'Iniciá sesión para ver tu panel.');
        }
        $authId = (int) $auth->id;

        // Detectar UNA VEZ y cachear qué columna de texto usa eventos_honor
        $ehTextCol = Cache::remember('schema:eventos_honor:textcol', self::SCHEMA_TTL, function () {
            try {
                if (Schema::hasColumn('eventos_honor', 'reason'))
                    return 'reason';
                if (Schema::hasColumn('eventos_honor', 'nota'))
                    return 'nota';
            } catch (\Throwable) {
                // si falla el schema, seguimos sin columna de texto
            }
            return null;
        });

        $u = Usuario::query()
            ->select(['id', 'name', 'email', 'username', 'celular', 'created_at'])
            ->with([
                // Últimas inscripciones del usuario (confirmadas primero)
                'inscripciones' => function ($q) {
                    // Orden estable y limitado
                    $q->select(['id', 'mesa_id', 'user_id', 'is_waiting', 'moderated_at', 'created_at'])
                        ->orderBy('is_waiting')      // false(0) primero → confirmadas
                        ->orderByDesc('created_at')  // más recientes primero
                        ->limit(self::INSCRIPCIONES_LIMIT)
                        ->with([
                            // Datos mínimos de la mesa
                            'mesa:id,title,image_path,capacity,is_open,manager_counts_as_player',
                        ]);
                },
                // Últimos eventos de honor (columnas usadas)
                'eventosHonor' => function ($q) use ($ehTextCol) {
                    $cols = ['id', 'user_id', 'mesa_id', 'slug', 'delta', 'occurred_at'];
                    if ($ehTextCol)
                        $cols[] = $ehTextCol; // reason/nota
                    $q->select($cols)
                        ->orderByDesc('occurred_at')
                        ->orderByDesc('id')
                        ->limit(self::EVENTOS_LIMIT);
                },
            ])
            // Contador barato de inscripciones confirmadas
            ->withCount([
                'inscripciones as inscripciones_confirmadas_count' => fn($q) => $q->where('is_waiting', false),
            ])
            ->findOrFail($authId);

        return view('dashboard.show', ['user' => $u]);
    }
}
