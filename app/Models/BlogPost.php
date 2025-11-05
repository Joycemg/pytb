<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

final class BlogPost extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'theme',
        'accent_color',
        'accent_text_color',
        'hero_image_url',
        'hero_image_caption',
        'published_at',
        'is_community',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'approved_at' => 'datetime',
        'is_community' => 'bool',
    ];

    protected static function booted(): void
    {
        static::creating(function (BlogPost $post): void {
            if (empty($post->slug)) {
                $post->slug = static::makeUniqueSlug($post->title ?? '');
            }
        });

        static::updating(function (BlogPost $post): void {
            if ($post->isDirty('title') && empty($post->slug)) {
                $post->slug = static::makeUniqueSlug($post->title ?? '');
            }
        });
    }

    private static function makeUniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: Str::random(8);
        $slug = $base;
        $count = 1;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $count++;
            $slug = $base . '-' . $count;
        }

        return $slug;
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'user_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(BlogAttachment::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(BlogPostComment::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(BlogPostLike::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'approved_by');
    }

    /**
     * @return BelongsToMany<BlogTag>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BlogTag::class);
    }

    protected function excerptComputed(): Attribute
    {
        return Attribute::get(function () {
            if (!empty($this->excerpt)) {
                return $this->excerpt;
            }

            $text = strip_tags((string) $this->content);
            return Str::limit(trim($text), 180);
        });
    }

    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeCommunity($query)
    {
        return $query->where('is_community', true);
    }

    public function scopePendingApproval($query)
    {
        return $query->where('is_community', true)
            ->whereNull('approved_at');
    }
}
