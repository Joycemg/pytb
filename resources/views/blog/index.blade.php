@extends('layouts.app')

@section('title', 'Blog')

@section('content')
  @php
    $history = $history ?? [];
  @endphp

  <div class="page container blog-list">
    <header class="page-head">
      <h1 class="page-title">Novedades</h1>
      <p class="page-subtitle">Enterate de lo último que sucede en la taberna.</p>
    </header>

    <div class="blog-layout">
      <aside id="blog-history" class="blog-history" aria-label="Historial de publicaciones">
        <h2 class="blog-history-title">Historial</h2>

        @if (!empty($history))
          <nav class="blog-history-groups">
            @foreach ($history as $yearGroup)
              <section class="blog-history-year">
                <h3 class="blog-history-year-title">{{ $yearGroup['year'] }}</h3>

                @foreach ($yearGroup['months'] as $monthGroup)
                  <details class="blog-history-month" {{ $loop->first ? 'open' : '' }}>
                    <summary class="blog-history-month-summary">{{ ucfirst($monthGroup['label']) }}</summary>
                    <ul class="blog-history-list">
                      @foreach ($monthGroup['posts'] as $historyPost)
                        @php
                          $historyDate = optional($historyPost['published_at']);
                        @endphp
                        <li class="blog-history-item">
                          <a href="{{ route('blog.show', ['post' => $historyPost['slug']]) }}" class="blog-history-link">
                            <span class="blog-history-post-title">{{ $historyPost['title'] }}</span>
                            @if ($historyDate)
                              <time class="blog-history-post-date" datetime="{{ $historyDate->toDateString() }}">{{ $historyDate->translatedFormat('d \d\e M') }}</time>
                            @endif
                          </a>
                        </li>
                      @endforeach
                    </ul>
                  </details>
                @endforeach
              </section>
            @endforeach
          </nav>
        @else
          <p class="blog-history-empty">Cuando publiques la primera entrada vas a ver el historial acá.</p>
        @endif
      </aside>

      <div id="blog-posts" class="blog-main">
        <section class="blog-cta blog-cta--compact" aria-label="Usá La Taberna como app">
          <div class="blog-cta-icon" aria-hidden="true">📲</div>
          <div class="blog-cta-body">
            <h2 class="blog-cta-title">Sumá La Taberna a tu inicio</h2>
            <p class="blog-cta-text">Guardá el blog como app para volver al instante a las novedades.</p>
            <p class="blog-cta-hint">En el menú de tu navegador, elegí <strong>Agregar a pantalla de inicio</strong>.</p>
          </div>
        </section>

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
            @php $hasHeroImage = filled($post->hero_image_url); @endphp
            <article class="card blog-card blog-theme-{{ $theme }} {{ $hasHeroImage ? '' : 'blog-card--no-media' }} {{ $loop->first ? 'blog-card--featured' : '' }}" style="--blog-accent: {{ $accent }}; --blog-accent-text: {{ $accentText }};">
              <div class="blog-card-inner">
                @if ($hasHeroImage)
                  <figure class="blog-card-media">
                    <img src="{{ $post->hero_image_url }}" alt="" loading="lazy">
                  </figure>
                @endif

                <div class="card-body">
                  <header class="blog-card-header">
                    <p class="blog-card-meta">
                      @php $publishedAt = $post->published_at?->timezone(config('app.timezone', 'UTC')); @endphp
                      <span class="blog-card-author">Por {{ $post->author->name ?? 'Equipo de La Taberna' }}</span>
                      @if ($publishedAt)
                        <span class="blog-card-separator" aria-hidden="true">•</span>
                        <time datetime="{{ $publishedAt->toIso8601String() }}">{{ $publishedAt->translatedFormat('d \d\e F, Y H:i') }}</time>
                      @endif
                    </p>

                    <h2 class="blog-card-title">{{ $post->title }}</h2>
                  </header>

                  <p class="blog-card-excerpt">{{ $post->excerpt_computed }}</p>

                  <footer class="blog-card-footer" aria-hidden="true">
                    <span class="blog-card-cta">Seguir leyendo</span>
                  </footer>
                </div>
              </div>

              <a class="blog-card-link" href="{{ route('blog.show', ['post' => $post->slug]) }}">
                <span class="sr-only">Leer la publicación {{ $post->title }}</span>
              </a>
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
    </div>
  </div>
@endsection
