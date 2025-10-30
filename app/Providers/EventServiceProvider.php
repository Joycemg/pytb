<?php declare(strict_types=1);

namespace App\Providers;

use App\Events\UsuarioAprobado;
use App\Listeners\EnviarMailCuentaAprobada;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

final class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        UsuarioAprobado::class => [EnviarMailCuentaAprobada::class],
    ];

    protected $shouldDiscoverEvents = false;
}
