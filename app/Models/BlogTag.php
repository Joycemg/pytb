<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

final class BlogTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    protected static function booted(): void
    {
        static::saving(function (BlogTag $tag): void {
            $tag->name = Str::of((string) $tag->name)->squish()->toString();

            if (empty($tag->slug)) {
                $tag->slug = static::generateUniqueSlug($tag->name);
            }
        });
    }

    /**
     * @return BelongsToMany<BlogPost>
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(BlogPost::class);
    }

    private static function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = Str::random(8);
        }

        $slug = $base;
        $counter = 1;

        while (static::where('slug', $slug)->exists()) {
            $counter++;
            $slug = $base . '-' . $counter;
        }

        return $slug;
    }
}
