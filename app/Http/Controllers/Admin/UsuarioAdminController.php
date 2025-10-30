<?php declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Events\UsuarioAprobado;
use App\Http\Controllers\Controller;
use App\Models\UserAudit;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

final class UsuarioAdminController extends Controller
{
    private const DEFAULT_PER_PAGE = 30;

    /** Valida la contraseña del admin/mod actual enviada como 'admin_password' (o 'password' como fallback). */
    private function assertAdminPassword(Request $r): ?RedirectResponse
    {
        $pwd = (string) $r->input('admin_password', '');
        if ($pwd === '')
            $pwd = (string) $r->input('password', ''); // fallback

        $r->merge(['__admin_pwd_checked' => $pwd]);
        $r->validate(['__admin_pwd_checked' => ['required', 'string', 'min:6']], [
            '__admin_pwd_checked.required' => 'Falta confirmar con tu contraseña.',
        ]);

        $user = $r->user();
        if (!$user || !Hash::check($pwd, $user->password)) {
            return back()->withInput()->with('error', 'Contraseña inválida. No se realizó la acción.');
        }
        return null;
    }

    /** Listado general (q, rol, estado, per_page) */
    public function index(Request $r): View
    {
        $this->authorize('viewAdmin', Usuario::class);

        $data = $r->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'rol' => ['nullable', Rule::in(['', 'user', 'moderator', 'admin'])],
            'estado' => ['nullable', Rule::in(['', 'pending', 'approved', 'locked', 'all'])],
            'per_page' => ['nullable', 'integer'],
        ]);

        $q = trim((string) ($data['q'] ?? ''));
        $rol = (string) ($data['rol'] ?? '');
        $estado = (string) ($data['estado'] ?? '');
        $perPage = $this->perPageFrom($r, self::DEFAULT_PER_PAGE);

        $hasUsername = Schema::hasColumn('usuarios', 'username');
        $hasCelular = Schema::hasColumn('usuarios', 'celular');
        $like = $q !== '' ? $this->likeTerm($q, 80) : null;

        $users = Usuario::query()
            ->when($q !== '', function ($qq) use ($like, $hasUsername) {
                $qq->where(function ($w) use ($like, $hasUsername) {
                    $w->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                    if ($hasUsername)
                        $w->orWhere('username', 'like', $like);
                });
            })
            ->when($rol !== '', fn($qq) => $qq->where('role', $rol))
            ->when($estado === 'pending', fn($qq) => $qq->whereNull('approved_at'))
            ->when($estado === 'approved', fn($qq) => $qq->whereNotNull('approved_at')->whereNull('locked_at'))
            ->when($estado === 'locked', fn($qq) => $qq->whereNotNull('locked_at'))
            ->select(array_values(array_filter([
                'id',
                'name',
                'email',
                'role',
                'approved_at',
                'locked_at',
                'created_at',
                $hasUsername ? 'username' : null,
                $hasCelular ? 'celular' : null,
            ])))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.usuarios.index', compact('users', 'q', 'rol', 'estado'));
    }

    /** Pendientes de aprobación con filtros y orden estable (created_at + id) */
    public function pendientes(Request $r): View
    {
        $this->authorize('approve', Usuario::class);

        $r->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer'],
            'sort' => ['nullable', Rule::in(['created_desc', 'created_asc'])],
        ]);

        $q = trim((string) $r->input('q', ''));
        $perPage = $this->perPageFrom($r, 30);
        $sort = (string) $r->input('sort', 'created_desc'); // 'created_desc' | 'created_asc'
        $dir = $sort === 'created_asc' ? 'asc' : 'desc';

        $hasUsername = Schema::hasColumn('usuarios', 'username');
        $hasCelular = Schema::hasColumn('usuarios', 'celular');
        $like = $q !== '' ? $this->likeTerm($q, 80) : null;

        $users = Usuario::query()
            ->whereNull('approved_at')
            ->when($q !== '', function ($qq) use ($like, $hasUsername) {
                $qq->where(function ($w) use ($like, $hasUsername) {
                    $w->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                    if ($hasUsername)
                        $w->orWhere('username', 'like', $like);
                });
            })
            ->select(array_values(array_filter([
                'id',
                'name',
                'email',
                'created_at',
                $hasUsername ? 'username' : null,
                $hasCelular ? 'celular' : null,
            ])))
            ->orderBy('created_at', $dir) // orden principal
            ->orderBy('id', $dir)         // estable ante empates
            ->paginate($perPage)
            ->withQueryString();

        return view('usuarios.pendientes', [
            'users' => $users,
            'q' => $q,
            'per_page' => $perPage,
            'sort' => $sort,
        ]);
    }

    public function edit(Usuario $usuario): View
    {
        $this->authorize('updateBasic', $usuario);
        return view('admin.usuarios.edit', ['u' => $usuario]);
    }

    public function update(Request $r, Usuario $usuario): RedirectResponse
    {
        $this->authorize('updateBasic', $usuario);
        if ($resp = $this->assertAdminPassword($r))
            return $resp;

        $isAdmin = method_exists($r->user(), 'hasRole') ? $r->user()->hasRole('admin') : ($r->user()->role ?? null) === 'admin';
        $field = (string) $r->input('field', '');

        $allowedByRole = $isAdmin
            ? ['name', 'email', 'username', 'celular', 'honor']
            : ['email', 'username'];

        if (!in_array($field, $allowedByRole, true)) {
            return back()->with('error', 'Campo inválido o no permitido.');
        }

        switch ($field) {
            case 'name':
                $rules = ['name' => ['required', 'string', 'max:100']];
                break;
            case 'email':
                $rules = ['email' => ['required', 'email', 'max:150', Rule::unique('usuarios', 'email')->ignore($usuario->id)->whereNull('deleted_at')]];
                break;
            case 'username':
                $rules = ['username' => ['nullable', 'string', 'max:20', 'regex:/^[a-zA-Z0-9_.-]+$/', Rule::unique('usuarios', 'username')->ignore($usuario->id)->whereNull('deleted_at')]];
                break;
            case 'celular':
                $rules = ['celular' => ['required', 'string', 'max:30', 'regex:/^[0-9+()\\-\\s]{6,}$/']];
                break;
            case 'honor':
                $rules = ['honor' => ['required', 'integer', 'between:-100000,100000']];
                break;
            default:
                return back()->with('error', 'Campo no soportado.');
        }

        $messages = [
            'celular.regex' => 'El celular debe tener al menos 6 dígitos/símbolos válidos (+() - y espacios).',
            'username.regex' => 'El usuario solo puede tener letras, números, punto, guion y guion bajo.',
        ];

        $data = $r->validate($rules, $messages);
        $before = $usuario->getAttribute($field);

        if ($field === 'name') {
            $usuario->name = Str::of($data['name'])->squish()->toString();
        } elseif ($field === 'email') {
            $v = mb_strtolower(trim((string) $data['email']));
            if ($v !== $usuario->email)
                $usuario->email_verified_at = null;
            $usuario->email = $v;
        } elseif ($field === 'username') {
            $v = $data['username'] !== null ? mb_strtolower(trim((string) $data['username'])) : null;
            $usuario->username = ($v === '') ? null : $v;
        } elseif ($field === 'celular') {
            $usuario->celular = Str::of($data['celular'])->squish()->toString();
        } elseif ($field === 'honor') {
            $h = (int) round(((int) $data['honor']) / 10) * 10;
            $usuario->honor = max(-100000, min(100000, $h));
        }

        if ($usuario->isDirty()) {
            $usuario->save();
            UserAudit::log('user.update.basic', $r->user()->id, $usuario->id, [
                'fields' => [$field],
                'from' => $before,
                'to' => $usuario->getAttribute($field),
            ]);
        }

        return back()->with('ok', 'Campo actualizado');
    }

    /** Aprueba un usuario y dispara notificación por evento (sin colas) */
    public function approve(Request $r, Usuario $usuario): RedirectResponse
    {
        $this->authorize('approve', Usuario::class);
        if ($resp = $this->assertAdminPassword($r))
            return $resp;

        if ($usuario->approved_at === null) {
            $usuario->approved_at = now();
            $usuario->approved_by = $r->user()->id;
            $usuario->save();

            Cache::forget('admin.pending_users_count');
            UserAudit::log('approve', $r->user()->id, $usuario->id, []);

            Log::info('Aprobación individual: disparando UsuarioAprobado', ['uid' => $usuario->id, 'email' => $usuario->email]);
            event(new UsuarioAprobado($usuario)); // Listener envía el mail (send())
        }

        return back()->with('ok', 'Usuario aprobado');
    }

    public function setRole(Request $r, Usuario $usuario): RedirectResponse
    {
        $this->authorize('changeRole', Usuario::class);
        if ($resp = $this->assertAdminPassword($r))
            return $resp;

        $key = sprintf('rl:role:%d:%d', $r->user()->id, $usuario->id);
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return back()->with('error', 'Demasiados intentos, probá más tarde.');
        }
        RateLimiter::hit($key, 3600);

        $data = $r->validate(['role' => ['required', Rule::in(['user', 'moderator', 'admin'])]]);

        if ($usuario->id === $r->user()->id && $data['role'] !== 'admin') {
            return back()->with('error', 'No podés degradarte a vos mismo.');
        }
        if ($usuario->role === 'admin' && $data['role'] !== 'admin') {
            $admins = Usuario::where('role', 'admin')->whereNull('locked_at')->count();
            if ($admins <= 1)
                return back()->with('error', 'No podés degradar al último admin activo.');
        }

        $old = $usuario->role;
        $usuario->role = $data['role'];
        $usuario->save();

        UserAudit::log('role.set', $r->user()->id, $usuario->id, ['from' => $old, 'to' => $data['role']]);
        return back()->with('ok', 'Rol actualizado');
    }

    public function resetPassword(Request $r, Usuario $usuario): RedirectResponse
    {
        $this->authorize('resetPassword', Usuario::class);
        if ($resp = $this->assertAdminPassword($r))
            return $resp;

        $key = sprintf('rl:pwd:%d:%d', $r->user()->id, $usuario->id);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->with('error', 'Demasiados intentos de reset, probá más tarde.');
        }
        RateLimiter::hit($key, 3600);

        $data = $r->validate(['password' => ['required', 'string', 'min:8', 'max:100', 'confirmed']]);

        $usuario->password = \Illuminate\Support\Facades\Hash::make($data['password']);
        $usuario->setRememberToken(Str::random(60));
        $usuario->save();

        UserAudit::log('pwd.reset', $r->user()->id, $usuario->id, []);
        return back()->with('ok', 'Contraseña restablecida');
    }

    public function lock(Request $r, Usuario $usuario): RedirectResponse
    {
        $this->authorize('lock', $usuario);
        if ($resp = $this->assertAdminPassword($r))
            return $resp;

        $key = sprintf('rl:lock:%d:%d', $r->user()->id, $usuario->id);
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return back()->with('error', 'Demasiados intentos, probá más tarde.');
        }
        RateLimiter::hit($key, 3600);

        if ($usuario->id === $r->user()->id) {
            return back()->with('error', 'No podés bloquear tu propia cuenta.');
        }
        if ($usuario->role === 'admin') {
            $admins = Usuario::where('role', 'admin')->whereNull('locked_at')->count();
            if ($admins <= 1)
                return back()->with('error', 'No podés bloquear al último admin activo.');
        }

        $usuario->locked_at = now();
        $usuario->save();

        UserAudit::log('lock', $r->user()->id, $usuario->id, []);
        return back()->with('ok', 'Usuario bloqueado');
    }

    public function unlock(Request $r, Usuario $usuario): RedirectResponse
    {
        $this->authorize('unlock', $usuario);
        if ($resp = $this->assertAdminPassword($r))
            return $resp;

        $key = sprintf('rl:unlock:%d:%d', $r->user()->id, $usuario->id);
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return back()->with('error', 'Demasiados intentos, probá más tarde.');
        }
        RateLimiter::hit($key, 3600);

        $usuario->locked_at = null;
        $usuario->save();

        UserAudit::log('unlock', $r->user()->id, $usuario->id, []);
        return back()->with('ok', 'Usuario desbloqueado');
    }

    /** Acciones masivas (approve/role/lock/unlock/delete con afterCommit para mails) */
    public function bulk(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'min:1'],
            'action' => ['required', 'string', 'max:50'],
        ]);

        $this->authorize('bulkAction', [Usuario::class, $data['action']]);
        if ($resp = $this->assertAdminPassword($r))
            return $resp;

        $key = sprintf('rl:bulk:%d', $r->user()->id);
        if (RateLimiter::tooManyAttempts($key, 20)) {
            return back()->with('error', 'Demasiadas acciones masivas, probá más tarde.');
        }
        RateLimiter::hit($key, 3600);

        $ids = array_values(array_unique(array_map('intval', $data['ids'])));
        $actor = (int) $r->user()->id;
        $ids = array_values(array_filter($ids, fn($id) => $id !== $actor));
        if (empty($ids))
            return back()->with('error', 'No hay usuarios válidos.');

        $action = $data['action'];
        $roleNew = null;

        if (str_starts_with($action, 'role:')) {
            $roleNew = substr($action, 5);
            if (!in_array($roleNew, ['user', 'moderator', 'admin'], true)) {
                return back()->with('error', 'Rol inválido.');
            }
            $action = 'role';
        } elseif (!in_array($action, ['approve', 'lock', 'unlock', 'delete'], true)) {
            return back()->with('error', 'Acción inválida.');
        }

        foreach (array_chunk($ids, 500) as $batch) {

            $approvedIdsThisBatch = [];

            DB::transaction(function () use ($batch, $action, $roleNew, $actor, &$approvedIdsThisBatch) {
                $users = Usuario::whereIn('id', $batch)->get(['id', 'role', 'approved_at', 'locked_at']);
                $adminsActivos = Usuario::where('role', 'admin')->whereNull('locked_at')->count();

                foreach ($users as $u) {
                    $before = [
                        'role' => $u->role,
                        'approved_at' => $u->approved_at,
                        'locked_at' => $u->locked_at,
                    ];

                    if ($action === 'approve') {
                        if ($u->approved_at === null) {
                            $u->approved_at = now();
                            $u->approved_by = $actor;
                            $approvedIdsThisBatch[] = $u->id; // notificar después
                        }
                    } elseif ($action === 'role') {
                        if ($u->role === 'admin' && $roleNew !== 'admin') {
                            if ($adminsActivos <= 1) {
                                UserAudit::log('bulk.role.skip_last_admin', $actor, $u->id, []);
                                continue;
                            }
                            $adminsActivos--;
                        }
                        $u->role = $roleNew;
                    } elseif ($action === 'lock') {
                        if ($u->role === 'admin') {
                            if ($adminsActivos <= 1) {
                                UserAudit::log('bulk.lock.skip_last_admin', $actor, $u->id, []);
                                continue;
                            }
                            $adminsActivos--;
                        }
                        $u->locked_at = now();
                    } elseif ($action === 'unlock') {
                        $u->locked_at = null;
                        if ($u->role === 'admin')
                            $adminsActivos++;
                    } elseif ($action === 'delete') {
                        if ($u->role === 'admin') {
                            UserAudit::log('bulk.delete.skip_admin', $actor, $u->id, []);
                            continue;
                        }
                        if (Schema::hasColumn($u->getTable(), 'deleted_at')) {
                            $u->setAttribute('deleted_at', now());
                            $u->save();
                            UserAudit::log('bulk.delete', $actor, $u->id, []);
                        } else {
                            $u->locked_at = now();
                            $u->save();
                            UserAudit::log('bulk.delete.fallback_lock', $actor, $u->id, []);
                        }
                        continue;
                    }

                    $u->save();
                    UserAudit::log('bulk.' . $action, $actor, $u->id, [
                        'before' => $before,
                        'after' => [
                            'role' => $u->role,
                            'approved_at' => $u->approved_at,
                            'locked_at' => $u->locked_at,
                        ],
                    ]);
                }

                if (!empty($approvedIdsThisBatch)) {
                    DB::afterCommit(function () use ($approvedIdsThisBatch) {
                        $aprobados = Usuario::whereIn('id', $approvedIdsThisBatch)->get(['id', 'email', 'name']);
                        foreach ($aprobados as $usr) {
                            Log::info('Aprobación masiva: disparando UsuarioAprobado', ['uid' => $usr->id, 'email' => $usr->email]);
                            event(new UsuarioAprobado($usr));
                        }
                    });
                }
            }, 3);
        }

        Cache::forget('admin.pending_users_count');
        return back()->with('ok', 'Acción masiva ejecutada');
    }

    /* ===================== Helpers ===================== */

    /** per_page acotado al rango 6..60 */
    protected function perPageFrom(Request $r, int $default = self::DEFAULT_PER_PAGE): int
    {
        $pp = (int) $r->input('per_page', $default);
        return max(6, min(60, $pp));
    }

    /** LIKE escapando % y _ y truncando a $maxLen */
    protected function likeTerm(string $q, int $maxLen = 80): string
    {
        $t = trim($q);
        $t = function_exists('mb_substr') ? mb_substr($t, 0, $maxLen) : substr($t, 0, $maxLen);
        return '%' . addcslashes($t, '%_') . '%';
    }
}
