<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class UserAudit extends Model
{
    protected $table = 'user_audits';
    public $timestamps = false;

    protected $fillable = ['actor_id', 'target_id', 'action', 'meta', 'ip', 'ua', 'created_at'];
    protected $casts = [
        'actor_id' => 'integer',
        'target_id' => 'integer',
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public static function log(string $action, ?int $actorId, ?int $targetId, array $meta = []): void
    {
        try {
            $ip = (string) (request()->ip() ?? '');
            $ip = Str::limit($ip, 45, '');

            $ua = (string) (request()->userAgent() ?? '');
            $ua = Str::limit($ua, 255, '');

            static::create([
                'actor_id' => $actorId,
                'target_id' => $targetId,
                'action' => $action,
                'meta' => empty($meta) ? null : $meta,
                'ip' => $ip,
                'ua' => $ua,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // en hosting compartido preferimos no fallar ni spamear logs
        }
    }
}
