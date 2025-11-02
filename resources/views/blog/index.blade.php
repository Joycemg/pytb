@extends('layouts.app')

@section('title', 'Blog')

@section('content')
  @php
    $history = $history ?? [];
    $filters = $filters ?? [
      'input' => ['q' => ''],
      'applied' => ['search' => ''],
      'active' => false,
    ];
  @endphp

  @php
    $latestPost = $posts->first();
    $latestPublishedAt = optional(optional($latestPost)->published_at)?->timezone(config('app.timezone', 'UTC'));
    $suggestedTags = ($suggestedTags ?? collect())->filter(fn ($tag) => !empty($tag['name'] ?? ''));
  @endphp

  <div class="page container blog-list">
    <header class="blog-hero" aria-labelledby="blog-hero-title">
      <div class="blog-hero-content">
        <div class="blog-hero-copy">
          <p class="blog-hero-eyebrow">Blog de La Taberna</p>
          <h1 id="blog-hero-title" class="blog-hero-title">Novedades</h1>
          <p class="blog-hero-subtitle">Lo último de la taberna, en un vistazo.</p>

          <dl class="blog-hero-stats" aria-label="Indicadores del blog">
            <div class="blog-hero-stat">
              <dt>Publicaciones</dt>
              <dd>{{ number_format($posts->total()) }}</dd>
            </div>

            @if ($latestPublishedAt)
              <div class="blog-hero-stat">
                <dt>Última actualización</dt>
                <dd>{{ $latestPublishedAt->diffForHumans() }}</dd>
              </div>
            @endif
          </dl>
        </div>

        <form method="get" action="{{ route('blog.index') }}" class="blog-hero-search" aria-label="Buscar publicaciones">
          <div class="blog-filter-field blog-filter-field--search">
            <label for="filter-search">Buscá por título, etiqueta o autor</label>

            <div class="blog-filter-control">
              <input id="filter-search" type="search" name="q" value="{{ $filters['input']['q'] ?? '' }}" placeholder="Escribí algo como torneo, #evento o Juan">
              <button type="submit" class="btn sm blog-filter-submit">Buscar</button>
            </div>
          </div>

          @if (!empty($filters['active']))
            <div class="blog-filter-actions">
              <a class="blog-filter-reset" href="{{ route('blog.index') }}">Limpiar</a>
            </div>
          @endif
        </form>
      </div>

    @if (!$filters['active'] && $latestPost)
      @php
        $highlightPublishedAt = optional($latestPost->published_at)?->timezone(config('app.timezone', 'UTC'));
        $highlightAuthor = $latestPost->author->name ?? 'Equipo de La Taberna';
      @endphp

      <a class="blog-hero-highlight" href="{{ route('blog.show', ['post' => $latestPost->slug]) }}" aria-label="Última publicación: {{ $latestPost->title }}">
        <div class="blog-hero-highlight-body">
          <span class="blog-hero-highlight-label">Última publicación</span>

          <div class="blog-hero-highlight-header">
            <span class="blog-hero-highlight-title">
              {{ $latestPost->title }}
              <span class="blog-hero-highlight-title-meta">
                por {{ $highlightAuthor }}
                @if ($highlightPublishedAt)
                  <span class="blog-hero-highlight-title-sep" aria-hidden="true">-</span>
                  <time datetime="{{ $highlightPublishedAt->toIso8601String() }}">{{ $highlightPublishedAt->format('d/m/y, H:i') }}</time>
                @endif
              </span>
            </span>
          </div>
        </div>

        <span class="blog-hero-highlight-cta">Leer ahora</span>
      </a>
    @endif

      @if (!empty($filters['active']) && filled($filters['applied']['search'] ?? ''))
        <div class="blog-filter-meta">
          <div class="blog-filter-active" role="status" aria-live="polite">
            <span class="blog-filter-chip">Mostrando resultados para <strong>{{ $filters['applied']['search'] }}</strong></span>
            <a class="blog-filter-reset" href="{{ route('blog.index') }}">Quitar filtro</a>
          </div>
        </div>
      @elseif ($suggestedTags->isNotEmpty())
        <div class="blog-filter-suggestion" aria-label="Búsquedas sugeridas">
          <span class="blog-filter-suggestion-label">Tendencias</span>
          @foreach ($suggestedTags as $tag)
            @php $tagQuery = ['q' => '#' . ltrim($tag['name'], '#')]; @endphp
            <a class="blog-filter-suggestion-btn" href="{{ route('blog.index', $tagQuery) }}">#{{ $tag['name'] }}</a>
          @endforeach
        </div>
      @endif
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

        <div class="blog-feed" aria-live="polite">
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
                  @php $publishedAt = $post->published_at?->timezone(config('app.timezone', 'UTC')); @endphp

                  <h2 class="blog-card-title">
                    {{ $post->title }}
                    <span class="blog-card-title-meta">
                      por {{ $post->author->name ?? 'Equipo de La Taberna' }}
                      @if ($publishedAt)
                        <span class="blog-card-title-sep" aria-hidden="true">-</span>
                        <time datetime="{{ $publishedAt->toIso8601String() }}">{{ $publishedAt->format('d/m/y, H:i') }}</time>
                      @endif
                    </span>
                  </h2>
                </header>

                <p class="blog-card-excerpt">{{ $post->excerpt_computed }}</p>

                @if ($post->tags->isNotEmpty())
                  <ul class="blog-card-tags" aria-label="Etiquetas">
                    @foreach ($post->tags as $tag)
                      @php $tagQuery = ['q' => '#' . $tag->name]; @endphp
                      <li><a class="blog-card-tag" href="{{ route('blog.index', $tagQuery) }}">#{{ $tag->name }}</a></li>
                    @endforeach
                  </ul>
                @endif

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
            <div class="blog-empty" role="status" aria-live="polite">
              <div class="blog-empty-icon" aria-hidden="true">📝</div>
              <div class="blog-empty-body">
                @if (!empty($filters['active']) && filled($filters['applied']['search'] ?? ''))
                  <h2 class="blog-empty-title">No encontramos resultados para “{{ $filters['applied']['search'] }}”.</h2>
                  <p class="blog-empty-text">Revisá la ortografía, probá con una palabra diferente o explorá el historial para descubrir publicaciones anteriores.</p>
                  <div class="blog-empty-actions">
                    <a class="btn btn-primary" href="{{ route('blog.index') }}">Ver todas las novedades</a>
                    <a class="btn" href="#blog-history">Explorar historial</a>
                  </div>
                @else
                  <h2 class="blog-empty-title">Todavía no hay publicaciones.</h2>
                  <p class="blog-empty-text">Estamos preparando las primeras novedades. Guardá el blog como app para enterarte apenas publiquemos algo nuevo.</p>
                  <div class="blog-empty-actions">
                    <a class="btn btn-primary" href="#blog-history">Revisar historial</a>
                    <a class="btn" href="{{ route('home') }}">Volver al inicio</a>
                  </div>
                @endif
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
  </div>
@endsection
