<?php declare(strict_types=1);

namespace App\Console;

use App\Support\Mantenimiento;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Cache;

final class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // ====== Hostinger: usar TZ local para que el cron respete horarios ======
        $schedule->timezone(config('app.display_timezone', 'America/Argentina/La_Rioja'));

        $isProd = app()->isProduction();
        $envs = $isProd ? ['production'] : ['local', 'staging', 'production'];

        // (0) Heartbeat del scheduler: clave para diagnosticar si el cron corre
        $schedule->call(static function () {
            Cache::put('scheduler:last_ok', now()->toIso8601String(), now()->addHours(12));
        })
            ->name('scheduler:heartbeat')
            // desfasado al minuto 0,15,30,45 + 1 min (menos colisiones de pico)
            ->cron('1,16,31,46 * * * *')
            ->runInBackground()
            ->environments($envs)
            ->evenInMaintenanceMode();

        // (1) Decaimiento de honor (idempotente por día)
        $schedule->call(static fn() => Mantenimiento::decaerHonor())
            ->name('honor:decay')
            ->withoutOverlapping(10)
            // cada 15 min, pero desfasado 2 min (2,17,32,47)
            ->cron('2-59/15 * * * *')
            // evita horas de pico si querés (opcional)
            ->unlessBetween('19:30', '23:59')
            ->runInBackground()
            ->environments($envs)
            ->evenInMaintenanceMode()
            // si algo imprime mucho, solo logueá fallos
            ->emailOutputOnFailure(config('mail.from.address'));

        // (2) Cierre automático de mesas antiguas
        $schedule->call(static fn() => Mantenimiento::cerrarMesasAntiguas())
            ->name('mesas:auto-close')
            ->withoutOverlapping(10)
            // cada 5 min, desfasado 1 min (1,6,11,...)
            ->cron('1-59/5 * * * *')
            ->runInBackground()
            ->environments($envs)
            ->evenInMaintenanceMode();

        // (3) Drenar cola sin procesos residentes
        $schedule->command('queue:work --queue=default,emails --stop-when-empty --timeout=60 --memory=128 --sleep=3 --tries=1')
            ->name('queue:drain')
            ->withoutOverlapping(5)
            // cada 2 min, desfasado 3 (3,5,7,9,...)
            ->cron('3-59/2 * * * *')
            // fuera de horas pico (opcional: comenta si no lo necesitás)
            ->unlessBetween('19:00', '23:30')
            ->runInBackground()
            ->environments($envs)
            ->evenInMaintenanceMode();

        // (4) Limpiar fallidos (barato)
        $schedule->command('queue:prune-failed --hours=48')
            ->name('queue:prune-failed')
            ->dailyAt('04:10')
            ->runInBackground()
            ->environments($envs)
            ->evenInMaintenanceMode();

        // (5) Prune de modelos (si usás Prunable)
        $schedule->command('model:prune')
            ->name('model:prune')
            ->dailyAt('04:20')
            ->runInBackground()
            ->environments($envs)
            ->evenInMaintenanceMode();

        // (6) Refrescar caché del scheduler
        $schedule->command('schedule:clear-cache')
            ->name('schedule:clear-cache')
            ->weeklyOn(1, '04:00') // lunes 04:00
            ->runInBackground()
            ->environments($envs)
            ->evenInMaintenanceMode();

        $schedule->command('mesas:send-open-emails')
            ->everyMinute()
            ->withoutOverlapping()
            ->timezone(config('app.timezone'));

        // (7) (Opcional) Log de una única tarea “termómetro” para debug fino
        // Útil si necesitás inspección rápida sin llenar disco:
        // $schedule->command('about')
        //     ->name('debug:about')
        //     ->cron('8,23,38,53 * * * *')
        //     ->appendOutputTo(storage_path('logs/schedule_probe.log'))
        //     ->runInBackground()
        //     ->environments($envs)
        //     ->evenInMaintenanceMode();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
