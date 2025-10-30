<?php declare(strict_types=1);

namespace App\Listeners;

use App\Events\UsuarioAprobado;
use App\Mail\CuentaAprobadaMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class EnviarMailCuentaAprobada
{
    public function handle(UsuarioAprobado $event): void
    {
        $email = (string) $event->user->email;
        if ($email === '') {
            return;
        }

        Log::info('UsuarioAprobado => mail a ' . $email);

        try {
            Mail::to($email)->send(new CuentaAprobadaMail($event->user));
        } catch (\Throwable $e) {
            // Fallback: intenta enviar versión texto plano minimal
            try {
                Mail::raw(
                    "¡Tu cuenta fue aprobada!\nIngresá: " . route('mesas.index'),
                    static function ($m) use ($email) {
                        $m->to($email)->subject('Cuenta aprobada en La Taberna');
                    }
                );
            } catch (\Throwable $e2) {
                Log::error('Error enviando mail (fallback) a ' . $email . ' : ' . $e2->getMessage());
            }
            Log::error('Error enviando mail a ' . $email . ' : ' . $e->getMessage());
        }
    }
}
