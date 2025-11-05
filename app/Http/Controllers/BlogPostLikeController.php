<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class BlogPostLikeController extends Controller
{
    public function __invoke(Request $request, BlogPost $post): RedirectResponse
    {
        if ($post->published_at === null || $post->published_at->isFuture()) {
            abort(404);
        }

        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        $like = $post->likes()
            ->where('user_id', $user->id)
            ->first();

        if ($like !== null) {
            $like->delete();
            $message = 'Quitaste tu “me gusta”.';
        } else {
            $post->likes()->create([
                'user_id' => $user->id,
            ]);
            $message = '¡Marcaste “me gusta”!';
        }

        return redirect()
            ->route('blog.show', ['post' => $post->slug])
            ->with('ok', $message);
    }
}
