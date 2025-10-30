<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushSubscription extends Model
{
    // Si tu tabla es 'push_subscriptions' podés omitir esto.
    protected $table = 'push_subscriptions';

    protected $fillable = [
        'endpoint',
        'p256dh',
        'auth',
        'content_encoding',
        'subscribable_id',
        'subscribable_type',
    ];

    protected $casts = [
        'endpoint' => 'string',
        'p256dh' => 'string',
        'auth' => 'string',
        'content_encoding' => 'string',
    ];

    public function subscribable()
    {
        return $this->morphTo();
    }
}
