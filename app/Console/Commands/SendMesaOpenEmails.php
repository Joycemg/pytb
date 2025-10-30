<?php declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Collection;
use Carbon\CarbonImmutable;
use App\Mail\MesasAperturaBatchMail;
use App\Models\Usuario;

class SendMesaOpenEmails extends Command
{
    protected $signature = 'mesas:send-open-emails';
    protected $description = 'Envía emails cuando mesas llegan a su hora de apertura; agrupa por minuto.';

    public function handle(): int
    {
        $now = CarbonImmutable::now();     // usa APP_TIMEZONE
        $windowStart = $now->subMinute();  // tolerancia de cron
        $windowEnd = $now;

        // 1) Mesas que “abren ahora” y no fueron notificadas
        $mesas = DB::table('mesas')
            ->select('id', 'title', 'opens_at')
            ->whereNull('opens_notified_at')
            ->whereNotNull('opens_at')
            ->whereBetween('opens_at', [$windowStart, $windowEnd])
            ->orderBy('opens_at')
            ->get();

        if ($mesas->isEmpty()) {
            $this->info('Sin mesas para notificar.');
            return self::SUCCESS;
        }

        // 2) Agrupar por minuto exacto
        $grupos = $mesas->groupBy(fn($m) => CarbonImmutable::parse($m->opens_at)->format('Y-m-d H:i'));

        // 3) Destinatarios (ajusta a tu gusto)
        //   Variante básica: todos los aprobados y no bloqueados
        $baseQueryDest = Usuario::query()
            ->whereNotNull('approved_at')
            ->whereNull('locked_at')
            ->select('id', 'name', 'email');

        foreach ($grupos as $minuto => $items) {
            /** @var \Illuminate\Support\Collection<int,array{id:int,title:string,url:string,opens_at:\Carbon\CarbonInterface}> $payload */
            $payload = $items->map(function ($m) {
                return [
                    'id' => (int) $m->id,
                    'title' => (string) $m->title,
                    'url' => URL::route('mesas.show', ['mesa' => $m->id]),
                    'opens_at' => CarbonImmutable::parse($m->opens_at),
                ];
            });

            // —— OPCIONES DE SEGMENTACIÓN ——
            // a) TODOS aprobados (actual)
            $destinatarios = (clone $baseQueryDest)->get();

            // b) Solo opt-in (ejemplo): $baseQueryDest->where('alerta_apertura', true)->get();
            // c) Watchlist de estas mesas:
            //   $userIds = DB::table('mesa_watchlist')->whereIn('mesa_id', $items->pluck('id'))->pluck('user_id')->unique();
            //   $destinatarios = (clone $baseQueryDest)->whereIn('id', $userIds)->get();

            // 4) Enviar (para test: send; en prod: queue + worker)
            foreach ($destinatarios as $user) {
                Mail::to($user->email)->send(new MesasAperturaBatchMail($user, $payload));
                // Producción:
                // Mail::to($user->email)->queue(new MesasAperturaBatchMail($user, $payload));
            }

            // 5) Marcar mesas como notificadas (idempotencia)
            DB::table('mesas')
                ->whereIn('id', $items->pluck('id'))
                ->update(['opens_notified_at' => now()]);
        }

        $this->info('Notificaciones de apertura enviadas.');
        return self::SUCCESS;
    }
}
