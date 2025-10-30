<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\UserAudit;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

final class UsuariosAprobacionController extends Controller
{
    private const PER_PAGE = 30;
    private const MAX_PER_PAGE = 100;
    private const RL_GLOBAL_MAX = 60;
    private const RL_TARGET_MAX = 15;
    private const RL_WINDOW_SEC = 3600;

    public function index(Request $r): View
    {
        $this->authorize('approve', Usuario::class);

        $q = trim((string) $r->input('q', ''));
        $perPage = (int) max(5, min((int) $r->input('per_page', self::PER_PAGE), self::MAX_PER_PAGE));

        $users = Usuario::pendientes()
            ->select(['id', 'name', 'email', 'username', 'celular', 'created_at', 'approved_at', 'locked_at'])
            ->when($q !== '', function ($qq) use ($q) {
                $like = '%' . addcslashes(mb_substr($q, 0, 80), '%_') . '%';
                $qq->where(function ($w) use ($like) {
                    $w->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('username', 'like', $like)
                        ->orWhere('celular', 'like', $like);
                });
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('usuarios.pendientes', compact('users', 'q', 'perPage'));
    }

    public function aprobar(Request $r, Usuario $usuario): RedirectResponse
    {
        $this->authorize('approve', Usuario::class);

        $actorId = (int) $r->user()->id;
        $keyGlobal = sprintf('rl:approve:actor:%d', $actorId);
        $keyTarget = sprintf('rl:approve:actor:%d:target:%d', $actorId, (int) $usuario->id);

        if (
            RateLimiter::tooManyAttempts($keyGlobal, self::RL_GLOBAL_MAX)
            || RateLimiter::tooManyAttempts($keyTarget, self::RL_TARGET_MAX)
        ) {
            return back()->with('error', 'Demasiados intentos de aprobación. Probá más tarde.');
        }

        if ($usuario->id === $actorId) {
            return back()->with('error', 'No podés aprobar tu propia cuenta.');
        }

        if (!is_null($usuario->locked_at)) {
            return back()->with('error', 'No se puede aprobar un usuario bloqueado.');
        }

        if (!is_null($usuario->approved_at)) {
            return back()->with('ok', 'El usuario ya estaba aprobado.');
        }

        try {
            DB::transaction(function () use ($usuario, $actorId) {
                $updated = Usuario::where('id', $usuario->id)
                    ->whereNull('approved_at')
                    ->update(['approved_at' => now(), 'approved_by' => $actorId]);

                if ($updated > 0) {
                    UserAudit::log('approve', $actorId, $usuario->id, []);
                }
            }, 3);
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo aprobar al usuario. Intentá nuevamente.');
        }

        RateLimiter::hit($keyGlobal, self::RL_WINDOW_SEC);
        RateLimiter::hit($keyTarget, self::RL_WINDOW_SEC);

        return back()->with('ok', 'Aprobado');
    }
}
