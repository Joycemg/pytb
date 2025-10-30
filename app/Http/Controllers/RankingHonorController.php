<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class RankingHonorController extends Controller
{
    /** Mantener el nombre original */
    private const PER_PAGE = 20;
    /** Límite superior opcional para per_page (sin cambiar nombres existentes) */
    private const MAX_PER_PAGE = 100;

    public function index(Request $r): View|RedirectResponse
    {
        // ── Búsqueda segura ────────────────────────────────────────────────
        $qTextRaw = trim((string) $r->query('q', ''));
        $qText = mb_substr($qTextRaw, 0, 80);
        $qEsc = addcslashes($qText, '%_');
        $like = $qEsc !== '' ? "%{$qEsc}%" : null;

        // ── Paginación configurable con helper tipado + clamp ──────────────
        // (evita el warning de tipo en Request::query)
        $perPage = $this->intOr($r, 'per_page', self::PER_PAGE, 5, self::MAX_PER_PAGE);

        // ── Flags de esquema (cache estático por request) ─────────────────
        static $hasIsCounted = null, $hasHonorTotal = null, $hasHonor = null, $hasApproved = null, $hasLocked = null, $hasDeleted = null;
        if ($hasIsCounted === null) {
            $hasIsCounted = Schema::hasColumn('eventos_honor', 'is_counted');
            $hasHonorTotal = Schema::hasColumn('usuarios', 'honor_total');
            $hasHonor = Schema::hasColumn('usuarios', 'honor');
            $hasApproved = Schema::hasColumn('usuarios', 'approved_at');
            $hasLocked = Schema::hasColumn('usuarios', 'locked_at');
            $hasDeleted = Schema::hasColumn('usuarios', 'deleted_at');
        }

        // ── Subconsulta SUM(delta) por usuario (filtra is_counted si existe) ─
        $buildSumSub = function () use ($hasIsCounted) {
            $q = DB::table('eventos_honor as eh')
                ->select('eh.user_id', DB::raw('SUM(eh.delta) AS pts'));
            if ($hasIsCounted) {
                $q->where('eh.is_counted', true);
            }
            return $q->groupBy('eh.user_id');
        };

        // ── Expresión del total coherente (COALESCE por preferencia) ───────
        $totalExpr = 'COALESCE(eh.pts'
            . ($hasHonorTotal ? ', usuarios.honor_total' : '')
            . ($hasHonor ? ', usuarios.honor' : '')
            . ', 0)';

        // ── Autoredirección a "mi" página si no hay búsqueda ni page ───────
        $me = $r->user();
        if ($me && $qEsc === '' && !$r->has('page')) {
            $meId = (int) $me->id;

            $myTotal = (int) DB::table('usuarios')
                ->from('usuarios')
                ->when($hasDeleted, fn($q) => $q->whereNull('usuarios.deleted_at'))
                ->when($hasApproved, fn($q) => $q->whereNotNull('usuarios.approved_at'))
                ->when($hasLocked, fn($q) => $q->whereNull('usuarios.locked_at'))
                ->leftJoinSub($buildSumSub(), 'eh', fn($j) => $j->on('eh.user_id', '=', 'usuarios.id'))
                ->where('usuarios.id', $meId)
                ->selectRaw("$totalExpr AS total")
                ->value('total');

            $above = (int) DB::table('usuarios')
                ->from('usuarios')
                ->when($hasDeleted, fn($q) => $q->whereNull('usuarios.deleted_at'))
                ->when($hasApproved, fn($q) => $q->whereNotNull('usuarios.approved_at'))
                ->when($hasLocked, fn($q) => $q->whereNull('usuarios.locked_at'))
                ->leftJoinSub($buildSumSub(), 'eh', fn($j) => $j->on('eh.user_id', '=', 'usuarios.id'))
                ->whereRaw("$totalExpr > ?", [$myTotal])
                ->count();

            $page = intdiv(max(0, $above), $perPage) + 1;
            if ($page > 1) {
                return redirect()->to(route('ranking', ['page' => $page, 'per_page' => $perPage]) . '#yo');
            }
        }

        // ── Consulta principal del ranking ─────────────────────────────────
        $users = Usuario::query()
            ->from('usuarios')
            ->when($hasDeleted, fn($q) => $q->whereNull('usuarios.deleted_at'))
            ->when($hasApproved, fn($q) => $q->whereNotNull('usuarios.approved_at'))
            ->when($hasLocked, fn($q) => $q->whereNull('usuarios.locked_at'))
            ->leftJoinSub($buildSumSub(), 'eh', fn($j) => $j->on('eh.user_id', '=', 'usuarios.id'))
            ->select(
                'usuarios.id',
                'usuarios.name',
                'usuarios.username',
                ...($hasHonorTotal ? ['usuarios.honor_total'] : []),
                ...(!$hasHonorTotal && $hasHonor ? ['usuarios.honor'] : [])
            )
            ->selectRaw("$totalExpr AS honor_total_calc")
            ->when($like !== null, fn($q) => $q->where(function ($w) use ($like) {
                $w->where('usuarios.name', 'like', $like)
                    ->orWhere('usuarios.username', 'like', $like);
            }))
            ->orderByDesc('honor_total_calc')
            ->orderBy('usuarios.name')
            ->orderBy('usuarios.id')
            ->paginate($perPage)
            ->withQueryString();

        return view('ranking.index', ['users' => $users]);
    }
}
