<?php declare(strict_types=1);

namespace App\Providers;

use App\Models\BlogPost;
use App\Models\Inscripcion;
use App\Models\Jornada;
use App\Models\Mesa;
use App\Policies\BlogPostPolicy;
use App\Policies\InscripcionPolicy;
use App\Policies\JornadaPolicy;
use App\Policies\MesaPolicy;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

final class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Mesa::class => MesaPolicy::class,
        Inscripcion::class => InscripcionPolicy::class,
        Jornada::class => JornadaPolicy::class,
        \App\Models\Usuario::class => \App\Policies\UsuarioPolicy::class,
        \App\Models\UserAudit::class => \App\Policies\UserAuditPolicy::class,
        BlogPost::class => BlogPostPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        $superAbility = trim((string) config('auth.super_ability', env('AUTH_SUPER_ABILITY', 'superadmin')));
        $adminRolesCsv = (string) config('auth.admin_roles', env('AUTH_ADMIN_ROLES', 'admin'));
        $adminRoles = array_values(array_filter(array_map(
            static fn(string $r) => mb_strtolower(trim($r), 'UTF-8'),
            $adminRolesCsv === '' ? [] : explode(',', $adminRolesCsv)
        )));

        Gate::before(function (?AuthenticatableContract $user, string $ability) use ($superAbility, $adminRoles) {
            if ($user === null) {
                return null;
            }

            if (method_exists($user, 'hasAbility') && $superAbility !== '' && $user->hasAbility($superAbility)) {
                return true;
            }
            if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                return true;
            }

            $roles = [];
            if (method_exists($user, 'rolesArray')) {
                $roles = (array) $user->rolesArray();
            } else {
                $maybeRoles = $user->roles ?? null;
                $maybeRole = $user->role ?? null;

                if (is_string($maybeRoles) && $maybeRoles !== '') {
                    $roles = array_values(array_filter(array_map('trim', explode(',', $maybeRoles))));
                } elseif (is_string($maybeRole) && $maybeRole !== '') {
                    $roles = [trim($maybeRole)];
                }
            }

            $rolesLower = array_map(static fn($r) => mb_strtolower((string) $r, 'UTF-8'), $roles);

            if ($superAbility !== '' && in_array(mb_strtolower($superAbility, 'UTF-8'), $rolesLower, true)) {
                return true;
            }

            if (!empty(array_intersect($adminRoles, $rolesLower))) {
                return null; // dejan decidir las policies
            }

            return null;
        });
    }
}
