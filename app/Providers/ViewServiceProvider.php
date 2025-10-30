<?php declare(strict_types=1);

namespace App\Providers;

use App\Models\Usuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

final class ViewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Comparte en TODAS las vistas la variable $pendingUsersCount para usuarios con permiso
        View::composer('*', function ($view) {
            $user = Auth::user();

            $count = null; // null = no mostrar nada
            if ($user && Gate::forUser($user)->allows('approve', Usuario::class)) {
                // Cache por 60s para no pegarle al DB en cada request
                $count = Cache::remember('pending_users_count', 60, function () {
                    return (int) Usuario::whereNull('approved_at')->count();
                });
            }

            $view->with('pendingUsersCount', $count);
        });
    }
}
