<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\SendPushPing;
use App\Models\PushSubscription;
use App\Services\WebPushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

final class SendPushPingTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_processes_subscriptions_in_configured_chunks(): void
    {
        $originalChunkSize = SendPushPing::$chunkSize;
        SendPushPing::$chunkSize = 2;

        try {
            $subscriptions = PushSubscription::factory()->count(5)->create();
            $ids = $subscriptions->pluck('id')->all();
            rsort($ids); // desordenado para forzar el ordenamiento por ID

            $sender = new class extends WebPushSender {
                public array $chunks = [];

                public function sendBatch(Collection $subscriptions): array
                {
                    $this->chunks[] = $subscriptions->pluck('id')->all();

                    return [
                        'sent' => 0,
                        'deleted' => 0,
                        'total' => $subscriptions->count(),
                    ];
                }
            };

            $job = new SendPushPing($ids);
            $job->handle($sender);

            $chunkSizes = array_map('count', $sender->chunks);
            self::assertSame([2, 2, 1], $chunkSizes);
        } finally {
            SendPushPing::$chunkSize = $originalChunkSize;
        }
    }

    public function test_handle_deletes_invalid_endpoints(): void
    {
        $originalChunkSize = SendPushPing::$chunkSize;
        SendPushPing::$chunkSize = 2;

        try {
            $subscriptions = PushSubscription::factory()->count(3)->create();
            $ids = $subscriptions->pluck('id')->all();

            $responses = [
                $ids[0] => 201,
                $ids[1] => 404,
                $ids[2] => 410,
            ];

            $sender = new class($responses) extends WebPushSender {
                /**
                 * @param  array<int, int>  $responses
                 */
                public function __construct(private array $responses)
                {
                }

                protected function sendSubscription(PushSubscription $subscription): int
                {
                    return $this->responses[$subscription->getKey()] ?? 0;
                }
            };

            $job = new SendPushPing($ids);
            $job->handle($sender);

            self::assertDatabaseCount('push_subscriptions', 1);
            self::assertDatabaseHas('push_subscriptions', ['id' => $ids[0]]);
            self::assertDatabaseMissing('push_subscriptions', ['id' => $ids[1]]);
            self::assertDatabaseMissing('push_subscriptions', ['id' => $ids[2]]);
        } finally {
            SendPushPing::$chunkSize = $originalChunkSize;
        }
    }
}
