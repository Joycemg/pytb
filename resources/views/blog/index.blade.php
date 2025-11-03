{{-- resources/views/blog/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Blog')

@push('head')
  <link rel="stylesheet" href="/assets/blog-history.css">
@endpush

@section('content')
  @php
    $history = $history ?? [];
    $filters = $filters ?? [
      'input' => ['q' => ''],
      'applied' => ['search' => ''],
      'active' => false,
    ];

    $latestPost = $posts->first();
    $latestPublishedAt = optional(optional($latestPost)->published_at)?->timezone(config('app.timezone', 'UTC'));
    $suggestedTags = ($suggestedTags ?? collect())->filter(fn($tag) => !empty($tag['name'] ?? ''));
  @endphp

  <div class="page container blog-list" itemscope itemtype="https://schema.org/Blog">
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

        {{-- Buscador --}}
        <form method="get" action="{{ route('blog.index') }}" class="blog-hero-search" role="search" aria-label="Buscar publicaciones">
          <div class="blog-filter-field blog-filter-field--search">
            <label for="filter-search">Buscá por título, etiqueta o autor</label>
            <div class="blog-filter-control">
              <input id="filter-search" type="search" name="q"
                     value="{{ $filters['input']['q'] ?? '' }}"
                     placeholder="Escribí algo como torneo, #evento o Juan"
                     autocomplete="off" />
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

      {{-- Highlight debajo del hero --}}
      @if (!$filters['active'] && $latestPost)
        @php
          $highlightPublishedAt = optional($latestPost->published_at)?->timezone(config('app.timezone', 'UTC'));
          $highlightAuthor = $latestPost->author->name ?? 'Equipo de La Taberna';
        @endphp

        <a class="blog-hero-highlight"
           href="{{ route('blog.show', ['post' => $latestPost->slug]) }}"
           aria-label="Última publicación: {{ $latestPost->title }}">
          <div class="blog-hero-highlight-body">
            <span class="blog-hero-highlight-label">Última publicación</span>
            <div class="blog-hero-highlight-header">
              <span class="blog-hero-highlight-title">
                {{ $latestPost->title }}
                <span class="blog-hero-highlight-title-meta">
                  por {{ $highlightAuthor }}
                  @if ($highlightPublishedAt)
                    <span class="blog-hero-highlight-title-sep" aria-hidden="true">-</span>
                    <time datetime="{{ $highlightPublishedAt->toIso8601String() }}">
                      {{ $highlightPublishedAt->format('d/m/y, H:i') }}
                    </time>
                  @endif
                </span>
              </span>
            </div>
          </div>
        </a>
      @endif

      {{-- Filtros / tendencias --}}
      @if (!empty($filters['active']) && filled($filters['applied']['search'] ?? ''))
        <div class="blog-filter-meta">
          <div class="blog-filter-active" role="status" aria-live="polite">
            <span class="blog-filter-chip">Mostrando resultados para
              <strong>{{ $filters['applied']['search'] }}</strong></span>
            <a class="blog-filter-reset" href="{{ route('blog.index') }}">Quitar filtro</a>
          </div>
        </div>
      @elseif ($suggestedTags->isNotEmpty())
        <div class="blog-filter-suggestion" aria-label="Tendencias">
          <span class="blog-filter-suggestion-label">Tendencias</span>
          @foreach ($suggestedTags as $tag)
            @php $tagQuery = ['q' => '#' . ltrim($tag['name'], '#')]; @endphp
            <a class="blog-filter-suggestion-btn" href="{{ route('blog.index', $tagQuery) }}">#{{ $tag['name'] }}</a>
          @endforeach
        </div>
      @endif
    </header>

    <div class="blog-layout">
      {{-- ===== HISTORIAL ===== --}}
      <aside id="blog-history" class="blog-history" aria-label="Historial de publicaciones">
        <h2 class="blog-history-title">Historial</h2>

        @if (!empty($history))
          <nav class="blog-history-groups">
            @foreach ($history as $yearGroup)
              @php
                $months = collect($yearGroup['months'] ?? []);
                $yearTotal = $months->sum(fn($m) => count($m['posts'] ?? []));
                $isFirstYear = $loop->first;
              @endphp

              <details class="blog-history-year" {{ $isFirstYear ? 'open' : '' }}>
                <summary class="blog-history-year-summary"
                         id="y-{{ $yearGroup['year'] }}"
                         role="button"
                         aria-expanded="{{ $isFirstYear ? 'true' : 'false' }}">
                  <h3 class="blog-history-year-title">
                    {{ $yearGroup['year'] }}
                    <span class="blog-history-year-count-inline">({{ $yearTotal }})</span>
                  </h3>
                </summary>

                <div class="blog-history-year-body">
                  @foreach ($months as $monthGroup)
                    @php
                      $monthLabel = ucfirst($monthGroup['label'] ?? '');
                      $postsInMonth = collect($monthGroup['posts'] ?? []);
                      $monthTotal = $postsInMonth->count();
                    @endphp

                    <details class="blog-history-month" {{ $loop->first && $isFirstYear ? 'open' : '' }}>
                      <summary class="blog-history-month-summary"
                               role="button"
                               aria-expanded="{{ $loop->first && $isFirstYear ? 'true' : 'false' }}">
                        <span class="blog-history-month-name">{{ $monthLabel }}</span>
                        <span class="blog-history-month-count"
                              aria-label="Publicaciones en {{ $monthLabel }}">{{ $monthTotal }}</span>
                      </summary>

                      @if ($monthTotal > 0)
                        <ul class="blog-history-list">
                          @foreach ($postsInMonth as $historyPost)
                            @php $historyDate = optional($historyPost['published_at']); @endphp
                            <li class="blog-history-item">
                              <a href="{{ route('blog.show', ['post' => $historyPost['slug']]) }}"
                                 class="blog-history-link">
                                <span class="blog-history-post-title">{{ $historyPost['title'] }}</span>
                                @if ($historyDate)
                                  <time class="blog-history-post-date" datetime="{{ $historyDate->toDateString() }}">
                                    {{ $historyDate->translatedFormat('d \d\e M') }}
                                  </time>
                                @endif
                              </a>
                            </li>
                          @endforeach
                        </ul>
                      @else
                        <p class="blog-history-empty">Sin publicaciones.</p>
                      @endif
                    </details>
                  @endforeach
                </div>
              </details>
            @endforeach
          </nav>
        @else
          <p class="blog-history-empty">Cuando publiques la primera entrada vas a ver el historial acá.</p>
        @endif
      </aside>

      {{-- ===== FEED (lista) ===== --}}
      <div id="blog-posts" class="blog-main" role="main" aria-describedby="blog-hero-title">
        <div class="blog-feed blog-feed--as-list" aria-live="polite">
          <ul class="post-list" itemscope itemtype="https://schema.org/Blog">
            @forelse ($posts as $post)
              @php
                $publishedAt = $post->published_at?->timezone(config('app.timezone', 'UTC'));
                $author = $post->author->name ?? 'Equipo de La Taberna';
              @endphp

              <li class="post-row" itemprop="blogPost" itemscope itemtype="https://schema.org/BlogPosting">
                <div class="post-row-left">
                  <a href="{{ route('blog.show', ['post' => $post->slug]) }}"
                     class="post-row-title" itemprop="headline">
                    {{ $post->title }}
                  </a>

                  <div class="post-row-meta">
                    <span class="post-row-author" itemprop="author">{{ $author }}</span>
                    @if ($publishedAt)
                      <span class="post-row-sep">·</span>
                      <time datetime="{{ $publishedAt->toIso8601String() }}" itemprop="datePublished">
                        {{ $publishedAt->format('d/m/y, H:i') }}
                      </time>
                    @endif
                  </div>

                  @if (filled($post->excerpt_computed))
                    <p class="post-row-excerpt" itemprop="description">
                      {{ $post->excerpt_computed }}
                    </p>
                  @endif

                  @if ($post->tags->isNotEmpty())
                    <ul class="post-row-tags" aria-label="Etiquetas">
                      @foreach ($post->tags as $tag)
                        @php $tagQuery = ['q' => '#' . $tag->name]; @endphp
                        <li><a class="post-row-tag" href="{{ route('blog.index', $tagQuery) }}">#{{ $tag->name }}</a></li>
                      @endforeach
                    </ul>
                  @endif
                </div>

                <div class="post-row-right">
                  <a href="{{ route('blog.show', ['post' => $post->slug]) }}" class="post-row-read">Leer</a>
                </div>
              </li>
            @empty
              <li class="post-list-empty">
                @if (!empty($filters['active']) && filled($filters['applied']['search'] ?? ''))
                  No encontramos resultados para “{{ $filters['applied']['search'] }}”.
                @else
                  Todavía no hay publicaciones.
                @endif
              </li>
            @endforelse
          </ul>

          <div class="blog-pagination">
            {{ $posts->links() }}
          </div>
        </div>

        {{-- CTA inferior --}}
        <section class="blog-cta blog-cta--compact" aria-label="Usá La Taberna como app">
          <div class="blog-cta-icon" aria-hidden="true">📱</div>
          <div class="blog-cta-content">
            <h2 class="blog-cta-title">Sumá La Taberna a tu inicio</h2>
            <p class="blog-cta-text">Guardá el blog como app para volver al instante a las novedades.</p>
            <p class="blog-cta-hint">En el menú de tu navegador, elegí <strong>Agregar a pantalla de inicio</strong>.</p>
          </div>
        </section>
      </div>
    </div>
  </div>

  {{-- JS inline: accesible y sincroniza aria-expanded --}}
  <script>
    (function () {
      function initSummaries(summarySelector, detailsSelector) {
        document.querySelectorAll(summarySelector).forEach(function (sum) {
          sum.setAttribute('tabindex', '0');
          sum.style.cursor = 'pointer';

          sum.addEventListener('click', function () {
            var d = sum.closest(detailsSelector);
            if (!(d instanceof HTMLDetailsElement)) return;
            requestAnimationFrame(function () {
              sum.setAttribute('aria-expanded', d.open ? 'true' : 'false');
            });
          });

          sum.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' || ev.key === ' ') {
              ev.preventDefault();
              var d = sum.closest(detailsSelector);
              if (!(d instanceof HTMLDetailsElement)) return;
              d.open = !d.open;
              sum.setAttribute('aria-expanded', d.open ? 'true' : 'false');
            }
          });
        });

        document.querySelectorAll(detailsSelector).forEach(function (d) {
          d.addEventListener('toggle', function () {
            var sum = d.querySelector(summarySelector);
            if (sum) sum.setAttribute('aria-expanded', d.open ? 'true' : 'false');
          });
        });
      }

      // Años y meses
      initSummaries('.blog-history-year > .blog-history-year-summary', '.blog-history-year');
      initSummaries('.blog-history-month > .blog-history-month-summary', '.blog-history-month');
    })();
  </script>
@endsection
