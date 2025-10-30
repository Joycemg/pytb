<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\BlogPost;
use App\Models\Usuario;

final class BlogPostPolicy
{
    public function viewAny(Usuario $user): bool
    {
        return $user->hasAnyRole(['admin', 'moderator']);
    }

    public function view(?Usuario $user, BlogPost $post): bool
    {
        if ($post->published_at !== null && !$post->published_at->isFuture()) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        if ($user->hasAnyRole(['admin', 'moderator'])) {
            return true;
        }

        return $user->id === $post->user_id;
    }

    public function create(Usuario $user): bool
    {
        return $user->hasAnyRole(['admin', 'moderator']);
    }

    public function update(Usuario $user, BlogPost $post): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('moderator')) {
            return $post->user_id === $user->id;
        }

        return false;
    }

    public function delete(Usuario $user, BlogPost $post): bool
    {
        return $this->update($user, $post);
    }
}
