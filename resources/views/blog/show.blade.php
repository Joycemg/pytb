@extends('layouts.app')

@section('title', $post->title)

@section('content')
  @php
    $theme = $post->theme ?? config('blog.default_theme', 'classic');
    $themes = (array) config('blog.themes', []);
    if (!array_key_exists($theme, $themes)) {
        $theme = config('blog.default_theme', 'classic');
    }
    $accent = $post->accent_color ?? ($themes[$theme]['accent'] ?? config('blog.default_accent'));
    $accentText = $post->accent_text_color ?? ($themes[$theme]['text'] ?? config('blog.default_text_color'));

    $rawContent = $post->content ?? '';
    $plainContent = trim(preg_replace('/\s+/', ' ', strip_tags((string) $rawContent)) ?? '');
    $wordCount = max(0, str_word_count($plainContent));
    $readingMinutes = max(1, (int) ceil($wordCount / 220));
  @endphp
  <article class="page container blog-post blog-theme-{{ $theme }}" style="--blog-accent: {{ $accent }}; --blog-accent-text: {{ $accentText }};">
    <header class="page-head">
      <h1 class="page-title">{{ $post->title }}</h1>
      <p class="blog-card-meta">
        @php $publishedAt = $post->published_at?->timezone(config('app.timezone', 'UTC')); @endphp
        <span>Por {{ $post->author->name ?? 'Equipo de La Taberna' }}</span>
        @if ($publishedAt)
          <span>· {{ $publishedAt->translatedFormat('d \d\e F, Y H:i') }}</span>
        @endif
        @if ($wordCount > 0)
          <span>· {{ $readingMinutes }} {{ \Illuminate\Support\Str::plural('minuto', $readingMinutes) }} de lectura</span>
        @endif
      </p>
    </header>

    @if ($post->hero_image_url)
      <figure class="blog-post-hero">
        <img src="{{ $post->hero_image_url }}" alt="{{ $post->hero_image_caption ?? '' }}">
        @if ($post->hero_image_caption)
          <figcaption>{{ $post->hero_image_caption }}</figcaption>
        @endif
      </figure>
    @endif

    @if (filled($post->excerpt))
      <p class="blog-post-lede">{{ $post->excerpt }}</p>
    @endif

    @php
      $rawContent = $post->content ?? '';
      $hasMarkup = str_contains((string) $rawContent, '<');
    @endphp
    <div class="blog-post-content">
      {!! $hasMarkup ? $rawContent : nl2br(e($rawContent)) !!}
    </div>

    @if ($post->attachments->isNotEmpty())
      <section class="blog-post-attachments">
        <h2>Archivos adjuntos</h2>
        <ul>
          @foreach ($post->attachments as $attachment)
            <li>
              <a href="{{ Storage::disk('public')->url($attachment->path) }}" target="_blank" rel="noopener">
                {{ $attachment->original_name }}
              </a>
              <span class="attachment-meta">({{ number_format($attachment->size / 1024, 1) }} KB)</span>
            </li>
          @endforeach
        </ul>
      </section>
    @endif

    <footer class="blog-post-footer">
      <a class="btn" href="{{ route('blog.index') }}">← Volver</a>
    </footer>
  </article>
  @endsection
