<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendPushPing;
use App\Models\PushSubscription;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class PushPingTest extends TestCase
{
    use RefreshDatabase;

    public function test_ping_requires_authorized_roles(): void
    {
        $user = Usuario::factory()->create(['role' => 'player']);

        $this->actingAs($user)
            ->postJson(route('push.ping'))
            ->assertForbidden();
    }

    public function test_ping_dispatches_job_with_subscription_ids(): void
    {
        Queue::fake();

        $user = Usuario::factory()->create(['role' => 'staff']);
        $subscriptions = PushSubscription::factory()->count(3)->create();
        $expectedIds = $subscriptions->pluck('id')->all();

        $this->actingAs($user)
            ->postJson(route('push.ping'))
            ->assertOk()
            ->assertJson([
                'queued' => true,
                'total' => count($expectedIds),
            ]);

        Queue::assertPushed(SendPushPing::class, function (SendPushPing $job) use ($expectedIds): bool {
            sort($expectedIds);
            $actual = $job->subscriptionIds;
            sort($actual);

            return $expectedIds === $actual;
        });
    }
}
