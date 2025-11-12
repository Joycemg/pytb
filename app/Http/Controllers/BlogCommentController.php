<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreBlogCommentRequest;
use App\Models\BlogPost;
use App\Models\BlogPostComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

        $existingComment = BlogPostComment::query()
            ->where('blog_post_id', $post->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingComment !== null) {
            return redirect()
                ->route('blog.show', ['post' => $post->slug])
                ->with('error', 'Ya dejaste tu comentario.');
        }

        $body = trim(strip_tags((string) $request->input('body')));

        BlogPostComment::create([
            'blog_post_id' => $post->id,
            'user_id' => $user->id,
            'body' => $body,
            'rating' => 0,
        ]);

        return redirect()
            ->route('blog.show', ['post' => $post->slug])
            ->with('ok', '¡Gracias por dejar tu comentario!');
    }

    public function destroy(Request $request, BlogPost $post, BlogPostComment $comment): RedirectResponse
    {
        $user = $request->user();
        if ($user === null || !$user->hasAnyRole(['admin', 'moderator'])) {
            abort(403);
        }

        if ($comment->blog_post_id !== $post->id) {
            abort(404);
        }

        $comment->delete();

        return redirect()
            ->route('blog.show', ['post' => $post->slug])
            ->with('ok', 'Comentario eliminado.');
    }
}
