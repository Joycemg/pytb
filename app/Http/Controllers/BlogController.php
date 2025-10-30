<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\BlogPostRequest;
use App\Models\BlogAttachment;
use App\Models\BlogPost;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class BlogController extends Controller
{
    public function home(Request $request): View
    {
        /** @var LengthAwarePaginator $posts */
        $posts = BlogPost::query()
            ->published()
            ->latest('published_at')
            ->with(['author'])
            ->paginate(10);

        if ($request->wantsJson()) {
            return view('blog.index', [
                'posts' => $posts,
            ]);
        }

        return view('blog.index', [
            'posts' => $posts,
        ]);
    }

    public function show(BlogPost $post): View
    {
        if ($post->published_at === null || $post->published_at->isFuture()) {
            $user = auth()->user();
            if ($user === null || (!$user->hasAnyRole(['admin', 'moderator']) && $user->id !== $post->user_id)) {
                abort(404);
            }
        }

        return view('blog.show', [
            'post' => $post->load(['author', 'attachments']),
        ]);
    }

    public function manage(): View
    {
        $this->authorize('viewAny', BlogPost::class);

        $posts = BlogPost::query()
            ->with(['author'])
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('blog.manage.index', [
            'posts' => $posts,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', BlogPost::class);

        return view('blog.manage.form', [
            'post' => new BlogPost(),
        ]);
    }

    public function store(BlogPostRequest $request): RedirectResponse
    {
        $this->authorize('create', BlogPost::class);

        $data = $request->validated();
        if (!empty($data['slug'])) {
            $data['slug'] = Str::slug($data['slug']);
        }

        $post = new BlogPost();
        $post->fill($data);
        $post->user_id = (int) $request->user()->id;
        $post->published_at = $data['published_at'] ?? now();
        $post->save();

        $this->syncAttachments($post, $request);

        return redirect()
            ->route('blog.manage')
            ->with('status', 'Entrada creada correctamente.');
    }

    public function edit(BlogPost $post): View
    {
        $this->authorize('update', $post);

        return view('blog.manage.form', [
            'post' => $post->load('attachments'),
        ]);
    }

    public function update(BlogPostRequest $request, BlogPost $post): RedirectResponse
    {
        $this->authorize('update', $post);

        $data = $request->validated();
        if (!empty($data['slug'])) {
            $data['slug'] = Str::slug($data['slug']);
        }

        $post->fill($data);
        if (empty($data['published_at'])) {
            if ($post->published_at === null) {
                $post->published_at = now();
            }
        } else {
            $post->published_at = $data['published_at'];
        }
        $post->save();

        $this->syncAttachments($post, $request);

        return redirect()
            ->route('blog.manage')
            ->with('status', 'Entrada actualizada.');
    }

    public function destroy(BlogPost $post): RedirectResponse
    {
        $this->authorize('delete', $post);

        foreach ($post->attachments as $attachment) {
            Storage::disk('public')->delete($attachment->path);
            $attachment->delete();
        }

        $post->delete();

        return redirect()
            ->route('blog.manage')
            ->with('status', 'Entrada eliminada.');
    }

    public function destroyAttachment(BlogPost $post, BlogAttachment $attachment): RedirectResponse
    {
        $this->authorize('update', $post);

        if ($attachment->blog_post_id !== $post->id) {
            abort(404);
        }

        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();

        return redirect()
            ->route('blog.edit', $post)
            ->with('status', 'Archivo eliminado.');
    }

    private function syncAttachments(BlogPost $post, Request $request): void
    {
        if (!$request->hasFile('attachments')) {
            return;
        }

        foreach ((array) $request->file('attachments', []) as $upload) {
            if ($upload === null) {
                continue;
            }

            $path = $upload->store('blog', 'public');
            $post->attachments()->create([
                'path' => $path,
                'original_name' => $upload->getClientOriginalName(),
                'mime_type' => $upload->getClientMimeType(),
                'size' => $upload->getSize(),
            ]);
        }
    }
}
