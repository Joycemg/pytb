@extends('layouts.app')

@section('title', 'Blog')

@section('content')
  @php
    use Illuminate\Support\Str;

    $history = $history ?? [];
  @endphp

  <div class="page container blog-list">
    <header class="page-head">
      <h1 class="page-title">Novedades</h1>
      <p class="page-subtitle">Enterate de lo último que sucede en la taberna.</p>
    </header>

    <div class="blog-layout">
      <aside class="blog-history" aria-label="Historial de publicaciones">
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

      <div class="blog-main">
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
              $themeLabel = $themes[$theme]['label'] ?? null;

              if ($themeLabel === null && !empty($theme)) {
                  $themeLabel = ucwords(str_replace(['-', '_'], ' ', $theme));
              }

              if (Str::lower((string) $themeLabel) === 'home') {
                  $themeLabel = null;
              }
            @endphp
            <article class="card blog-card blog-theme-{{ $theme }}" style="--blog-accent: {{ $accent }}; --blog-accent-text: {{ $accentText }};">
              @if ($post->hero_image_url)
                <figure class="blog-card-media">
                  <img src="{{ $post->hero_image_url }}" alt="" loading="lazy">
                </figure>
              @else
                <div class="blog-card-media blog-card-media--empty" aria-hidden="true"></div>
              @endif

              <div class="card-body">
                @if (!empty($themeLabel))
                  <span class="blog-card-tag">{{ $themeLabel }}</span>
                @endif

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
