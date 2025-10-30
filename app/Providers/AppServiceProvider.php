<?php declare(strict_types=1);

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // bindings si hiciera falta
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);

        if (app()->isLocal()) {
            Model::shouldBeStrict();
            Model::preventLazyLoading();
            Model::preventSilentlyDiscardingAttributes();
            Model::preventAccessingMissingAttributes();
        } else {
            Model::shouldBeStrict(false);
            Model::preventLazyLoading(false);
            Model::preventSilentlyDiscardingAttributes(false);
            Model::preventAccessingMissingAttributes(false);
        }

        // Zona horaria
        $tz = (string) config('app.timezone', 'UTC');
        if ($tz !== '') {
            date_default_timezone_set($tz);
            config(['app.timezone' => $tz]);
        }

        // Localización
        $locale = (string) config('app.locale', 'es');
        app()->setLocale($locale);
        Carbon::setLocale($locale);

        // Fuerza la raíz desde APP_URL
        if ($root = config('app.url')) {
            URL::forceRootUrl($root);
        }

        // Fuerza https si APP_URL es https
        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }



    }

}
