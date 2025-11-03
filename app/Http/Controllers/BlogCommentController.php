<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreBlogCommentRequest;
use App\Models\BlogPost;
use App\Models\BlogPostComment;
use Illuminate\Http\RedirectResponse;

final class BlogCommentController extends Controller
{
    public function store(StoreBlogCommentRequest $request, BlogPost $post): RedirectResponse
    {
        if ($post->published_at === null || $post->published_at->isFuture()) {
            abort(404);
        }

        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        $body = trim(strip_tags((string) $request->input('body')));

        /** @var BlogPostComment $comment */
        $comment = BlogPostComment::updateOrCreate(
            [
                'blog_post_id' => $post->id,
                'user_id' => $user->id,
            ],
            [
                'body' => $body,
                'rating' => (int) $request->input('rating'),
            ]
        );

        $message = $comment->wasRecentlyCreated
            ? '¡Gracias por dejar tu comentario!'
            : 'Actualizamos tu opinión.';

        return redirect()
            ->route('blog.show', ['post' => $post->slug])
            ->with('ok', $message);
    }
}
