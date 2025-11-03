{{-- resources/views/blog/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Blog')

@push('head')
  <link rel="stylesheet" href="/css/blog-history.css">
  <link rel="stylesheet" href="/css/blog-hero-filter.css">
@endpush

@section('content')
  @php
    $history = $history ?? [];
    $filters = $filters ?? [
      'input' => ['q' => ''],
      'applied' => ['search' => ''],
      'active' => false,
    ];
    $activeTab = $activeTab ?? 'novedades';
    $tabCounts = $tabCounts ?? ['novedades' => 0, 'miembros' => 0];
    $tabQueryDefaults = $tabQueryDefaults ?? [];

    $latestPost = $posts->first();
    $latestPublishedAt = optional(optional($latestPost)->published_at)?->timezone(config('app.timezone', 'UTC'));

    $heroTitle = $activeTab === 'miembros' ? 'Miembros' : 'Novedades';
    $heroSubtitle = $activeTab === 'miembros'
      ? 'Aportes y reseñas creadas por la comunidad.'
      : 'Lo último de la taberna, en un vistazo.';

    // Opcionales provistos desde el controller
    $topTrend = $topTrend ?? null;               // ['name' => 'LA TABERNA']
    $topContributor = $topContributor ?? null;   // ['name'=>'Marcelo','avatar'=>url,'count'=>2]
  @endphp

  <div class="page container blog-list" itemscope itemtype="https://schema.org/Blog">
    <header class="blog-hero" aria-labelledby="blog-hero-title">
      <div class="blog-hero-content">
        {{-- IZQUIERDA: título, stats y última publicación --}}
        <div class="blog-hero-copy">
          <p class="blog-hero-eyebrow">Blog de La Taberna</p>
          <h1 id="blog-hero-title" class="blog-hero-title">{{ $heroTitle }}</h1>
          <p class="blog-hero-subtitle">{{ $heroSubtitle }}</p>

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

          @if (!$filters['active'] && $latestPost)
            @php
              $hAt = optional($latestPost->published_at)?->timezone(config('app.timezone', 'UTC'));
              $hAuthor = $latestPost->author->name ?? 'Equipo de La Taberna';
              if ($latestPost->is_community) {
                $hAuthor = $latestPost->author->name ?? 'Miembro de la comunidad';
              }
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
                      por {{ $hAuthor }}
                      @if ($hAt)
                        <span class="blog-hero-highlight-title-sep" aria-hidden="true">-</span>
                        <time datetime="{{ $hAt->toIso8601String() }}">{{ $hAt->format('d/m/y, H:i') }}</time>
                      @endif
                    </span>
                  </span>
                </div>
              </div>
            </a>
          @endif
        </div>

        {{-- DERECHA: buscador + tendencia + top contributor --}}
        <div class="blog-hero-side">
          <form method="get" action="{{ route('blog.index') }}" class="blog-hero-search" role="search" aria-label="Buscar publicaciones">
            <div class="blog-filter-field blog-filter-field--search">
              <label for="filter-search">Buscá por título, etiqueta o autor</label>
              <div class="blog-filter-input">
                <input id="filter-search" type="search" name="q"
                       value="{{ $filters['input']['q'] ?? '' }}"
                       autocomplete="off"
                       class="blog-filter-input-control" />
                <button type="submit" class="blog-filter-input-action" aria-label="Buscar">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="blog-filter-input-icon">
                    <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 1-3.478 9.756l-2.384 2.384a.75.75 0 0 1-1.06-1.06l2.384-2.384A5.5 5.5 0 0 1 9 3.5Zm0 1.5a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z" clip-rule="evenodd"/>
                  </svg>
                </button>
              </div>
            </div>

            @if (!empty($filters['active']))
              <div class="blog-filter-actions">
                <a class="blog-filter-reset" href="{{ route('blog.index') }}">Limpiar</a>
              </div>
            @endif
          </form>

          @if (empty($filters['active']) && is_array($topTrend) && !empty($topTrend['name'] ?? ''))
            <div class="blog-filter-suggestion blog-filter-suggestion--in-hero" aria-label="Tendencia">
              <span class="blog-filter-suggestion-label">Tendencia</span>
              <a class="blog-filter-suggestion-btn" href="{{ route('blog.index', ['q' => '#'.ltrim($topTrend['name'], '#')]) }}">
                #{{ ltrim($topTrend['name'], '#') }}
              </a>
            </div>
          @endif

          @if (is_array($topContributor) && !empty($topContributor['name'] ?? ''))
            <div class="blog-top-contributor" aria-label="Miembro que más aportó">
              <span class="btc-label">Miembro que más aportó</span>
              <div class="btc-avatar" aria-hidden="true">
                @if (!empty($topContributor['avatar'] ?? null))
                  <img src="{{ $topContributor['avatar'] }}" alt="">
                @else
                  <div class="btc-avatar-fallback">👤</div>
                @endif
              </div>
              <span class="btc-name">{{ $topContributor['name'] }}</span>
              @if (!empty($topContributor['count']))
                <span class="btc-count">— {{ (int) $topContributor['count'] }} aportes</span>
              @endif
            </div>
          @endif
        </div>
      </div>

      @if (!empty($filters['active']) && filled($filters['applied']['search'] ?? ''))
        <div class="blog-filter-meta">
          <div class="blog-filter-active" role="status" aria-live="polite">
            <span class="blog-filter-chip">Mostrando resultados para <strong>{{ $filters['applied']['search'] }}</strong></span>
            <a class="blog-filter-reset" href="{{ route('blog.index') }}">Quitar filtro</a>
          </div>
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
                <summary class="blog-history-year-summary" id="y-{{ $yearGroup['year'] }}" role="button" aria-expanded="{{ $isFirstYear ? 'true' : 'false' }}">
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
                      <summary class="blog-history-month-summary" role="button" aria-expanded="{{ $loop->first && $isFirstYear ? 'true' : 'false' }}">
                        <span class="blog-history-month-name">{{ $monthLabel }}</span>
                        <span class="blog-history-month-count" aria-label="Publicaciones en {{ $monthLabel }}">({{ $monthTotal }})</span>
                      </summary>

                      @if ($monthTotal > 0)
                        <ul class="blog-history-list">
                          @foreach ($postsInMonth as $historyPost)
                            @php $historyDate = optional($historyPost['published_at']); @endphp
                            <li class="blog-history-item">
                              <a href="{{ route('blog.show', ['post' => $historyPost['slug']]) }}" class="blog-history-link">
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

      {{-- ===== FEED ===== --}}
      <div id="blog-posts" class="blog-main" role="main" aria-describedby="blog-hero-title">
        <div class="blog-feed blog-feed--as-list" aria-live="polite">
          @php
            $tabs = [
              'novedades' => [
                'label' => 'Novedades',
                'count' => $tabCounts['novedades'] ?? 0,
              ],
              'miembros' => [
                'label' => 'Miembros',
                'count' => $tabCounts['miembros'] ?? 0,
              ],
            ];

            $queryDefaults = collect($tabQueryDefaults)
              ->filter(fn($value) => $value !== null && $value !== '')
              ->all();
          @endphp

          <div class="blog-feed-head">
            <div class="blog-feed-tabs" role="tablist" aria-label="Tipo de publicaciones">
              @foreach ($tabs as $tabKey => $tabData)
                @php
                  $tabQuery = array_merge(
                    $queryDefaults,
                    $tabKey === 'novedades'
                      ? []
                      : ['tab' => $tabKey]
                  );
                  $tabUrl = route('blog.index', $tabQuery);
                  $isActive = $activeTab === $tabKey;
                @endphp
                <a href="{{ $tabUrl }}"
                   id="blog-tab-{{ $tabKey }}"
                   class="blog-feed-tab {{ $isActive ? 'is-active' : '' }}"
                   role="tab"
                   aria-selected="{{ $isActive ? 'true' : 'false' }}"
                   aria-controls="post-list-{{ $tabKey }}">
                  <span>{{ $tabData['label'] }}</span>
                  <span class="blog-feed-tab-count">{{ number_format($tabData['count']) }}</span>
                </a>
              @endforeach
            </div>

            @if ($activeTab === 'miembros')
              <div class="blog-feed-actions">
                <a class="btn" href="{{ route('blog.community') }}">Ver comunidad</a>
                @if ($canSubmitCommunity)
                  <a class="btn btn-primary" href="{{ route('blog.community.create') }}">Publicar mi aporte</a>
                @endif
              </div>
            @endif
          </div>

          @foreach ($tabs as $tabKey => $tabData)
            @php $isActive = $activeTab === $tabKey; @endphp
            <div id="post-list-{{ $tabKey }}"
                 class="blog-feed-panel"
                 role="tabpanel"
                 aria-labelledby="blog-tab-{{ $tabKey }}"
                 @if (!$isActive) hidden @endif
                 @if ($isActive) tabindex="0" @endif>
              @if ($isActive)
                <ul class="post-list" itemscope itemtype="https://schema.org/Blog">
                  @forelse ($posts as $post)
                    @php
                      $publishedAt = $post->published_at?->timezone(config('app.timezone', 'UTC'));
                      $author = $post->author->name ?? 'Equipo de La Taberna';
                      if ($post->is_community) {
                        $author = $post->author->name ?? 'Miembro de la comunidad';
                      }
                    @endphp

                    <li class="post-row" itemprop="blogPost" itemscope itemtype="https://schema.org/BlogPosting">
                      <div class="post-row-left">
                        <a href="{{ route('blog.show', ['post' => $post->slug]) }}" class="post-row-title" itemprop="headline">
                          {{ $post->title }}
                        </a>

                        <div class="post-row-meta">
                          @if ($post->is_community)
                            <span class="post-row-badge">Comunidad</span>
                            <span class="post-row-sep">·</span>
                          @endif
                          <span class="post-row-author" itemprop="author">{{ $author }}</span>
                          @if ($publishedAt)
                            <span class="post-row-sep">·</span>
                            <time datetime="{{ $publishedAt->toIso8601String() }}" itemprop="datePublished">{{ $publishedAt->format('d/m/y, H:i') }}</time>
                          @endif
                        </div>

                        @if (filled($post->excerpt_computed))
                          <p class="post-row-excerpt" itemprop="description">{{ $post->excerpt_computed }}</p>
                        @endif

                        @if ($post->tags->isNotEmpty())
                          <ul class="post-row-tags" aria-label="Etiquetas">
                            @foreach ($post->tags as $tag)
                              @php
                                $tagQuery = ['q' => '#' . $tag->name];
                                if ($activeTab === 'miembros') {
                                  $tagQuery['tab'] = 'miembros';
                                }
                              @endphp
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
                        @if ($activeTab === 'miembros')
                          Aún no hay aportes publicados.
                          @if ($canSubmitCommunity)
                            <a class="post-list-empty-link" href="{{ route('blog.community.create') }}">Compartí el primero</a>.
                          @else
                            <a class="post-list-empty-link" href="{{ route('blog.community') }}">Conocé cómo participar</a>.
                          @endif
                        @else
                          Todavía no hay publicaciones.
                        @endif
                      @endif
                    </li>
                  @endforelse
                </ul>

                <div class="blog-pagination">
                  {{ $posts->appends($activeTab === 'miembros' ? ['tab' => 'miembros'] : [])->links() }}
                </div>
              @endif
            </div>
          @endforeach
        </div>

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

      initSummaries('.blog-history-year > .blog-history-year-summary', '.blog-history-year');
      initSummaries('.blog-history-month > .blog-history-month-summary', '.blog-history-month');
    })();
  </script>
@endsection
