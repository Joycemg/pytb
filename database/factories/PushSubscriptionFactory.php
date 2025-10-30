<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PushSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PushSubscription>
 */
final class PushSubscriptionFactory extends Factory
{
    protected $model = PushSubscription::class;

    public function definition(): array
    {
        return [
            'endpoint' => 'https://example.test/web-push/' . Str::uuid(),
            'p256dh' => Str::random(87),
            'auth' => Str::random(22),
            'content_encoding' => 'aes128gcm',
        ];
    }
}
