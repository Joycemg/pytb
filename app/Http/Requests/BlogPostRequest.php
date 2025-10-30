<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BlogPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        return method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole(['admin', 'moderator'])
            : false;
    }

    public function rules(): array
    {
        $post = $this->route('post');
        $ignoreId = is_object($post) && method_exists($post, 'getKey') ? $post->getKey() : null;

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('blog_posts', 'slug')->ignore($ignoreId)],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'string'],
            'published_at' => ['nullable', 'date'],
            'attachments' => ['sometimes', 'array'],
            'attachments.*' => ['file', 'max:51200'],
        ];
    }
}
