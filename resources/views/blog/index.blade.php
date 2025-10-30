@extends('layouts.app')

@section('title', 'Blog')

@section('content')
  <div class="page container blog-list">
    <header class="page-head">
      <h1 class="page-title">Novedades</h1>
      <p class="page-subtitle">Enterate de lo último que sucede en la taberna.</p>
    </header>

    <div class="blog-grid">
      @forelse ($posts as $post)
        @php
          $theme = $post->theme ?? config('blog.default_theme', 'classic');
          $themes = (array) config('blog.themes', []);
          if (!array_key_exists($theme, $themes)) {
              $theme = config('blog.default_theme', 'classic');
          }
          $accent = $post->accent_color ?? ($themes[$theme]['accent'] ?? config('blog.default_accent'));
          $accentText = $post->accent_text_color ?? ($themes[$theme]['text'] ?? config('blog.default_text_color'));
        @endphp
        <article class="card blog-card blog-theme-{{ $theme }}" style="--blog-accent: {{ $accent }}; --blog-accent-text: {{ $accentText }};">
          <div class="card-body">
            <span class="blog-card-tag">{{ $themes[$theme]['label'] ?? ucfirst($theme) }}</span>
            <h2 class="blog-card-title">
              <a href="{{ route('blog.show', ['post' => $post->slug]) }}">{{ $post->title }}</a>
            </h2>

            <p class="blog-card-meta">
              @php $publishedAt = $post->published_at?->timezone(config('app.timezone', 'UTC')); @endphp
              <span>Por {{ $post->author->name ?? 'Equipo de La Taberna' }}</span>
              @if ($publishedAt)
                <span>· {{ $publishedAt->translatedFormat('d \d\e F, Y H:i') }}</span>
              @endif
            </p>

            <p class="blog-card-excerpt">{{ $post->excerpt_computed }}</p>

            <a class="btn btn-primary blog-card-link" href="{{ route('blog.show', ['post' => $post->slug]) }}">Leer más</a>
          </div>
          @if ($post->hero_image_url)
            <div class="blog-card-hero" aria-hidden="true">
              <img src="{{ $post->hero_image_url }}" alt="" loading="lazy">
            </div>
          @endif
        </article>
      @empty
        <div class="card">
          <div class="card-body">
            <p>No hay publicaciones todavía. ¡Vuelve pronto!</p>
          </div>
        </div>
      @endforelse
    </div>

    <div class="blog-pagination">
      {{ $posts->links() }}
    </div>
  </div>
@endsection
