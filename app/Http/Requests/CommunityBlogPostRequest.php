<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

final class CommunityBlogPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        if (!method_exists($user, 'estaAprobado')) {
            return false;
        }

        return $user->estaAprobado();
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'string'],
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

            $rawNewTags = preg_split('/[\n,;]+/', (string) $this->input('new_tags', '')) ?: [];
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
