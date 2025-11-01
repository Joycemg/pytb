<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'theme' => ['nullable', 'string', Rule::in(array_keys((array) config('blog.themes', [])))],
            'accent_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_text_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'hero_image_url' => ['nullable', 'url', 'max:500'],
            'hero_image_caption' => ['nullable', 'string', 'max:160'],
            'published_at' => ['nullable', 'date'],
            'attachments' => ['sometimes', 'array'],
            'attachments.*' => ['file', 'max:51200'],
            'tags' => ['nullable', 'array', 'max:3'],
            'tags.*' => ['integer', 'exists:blog_tags,id'],
            'new_tags' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $existing = collect($this->input('tags', []))
                ->filter(fn ($value) => is_numeric($value))
                ->map(fn ($value) => (int) $value)
                ->unique();

            $rawNewTags = preg_split('/[,;]+/', (string) $this->input('new_tags', '')) ?: [];
            $newTags = collect($rawNewTags)
                ->map(fn ($value) => Str::of((string) $value)->squish()->toString())
                ->filter(fn ($value) => $value !== '')
                ->unique(fn ($value) => Str::lower($value));

            if ($existing->count() + $newTags->count() > 3) {
                $validator->errors()->add('tags', 'Podés seleccionar hasta 3 etiquetas en total.');
            }
        });
    }
}
