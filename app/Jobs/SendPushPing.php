<?php declare(strict_types=1);

namespace App\Jobs;

use App\Models\PushSubscription;
use App\Services\WebPushSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

final class SendPushPing implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Tamaño del lote a procesar.
     */
    public static int $chunkSize = 100;

    /**
     * Cola dedicada para los envíos Web Push.
     */
    public string $queue = 'webpush';

    /**
     * @param  array<int, int|string>  $subscriptionIds
     */
    public function __construct(public array $subscriptionIds)
    {
    }

    public function handle(WebPushSender $sender): void
    {
        if (empty($this->subscriptionIds)) {
            return;
        }

        PushSubscription::query()
            ->whereKey($this->subscriptionIds)
            ->orderBy('id')
            ->chunkById(static::$chunkSize, function (Collection $subscriptions) use ($sender): void {
                $sender->sendBatch($subscriptions);
            });
    }
}
