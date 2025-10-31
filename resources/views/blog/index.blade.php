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

    <section class="blog-hero" aria-label="Destacados del blog">
      <div class="blog-hero-content">
        <p class="blog-hero-kicker">Historias &amp; actualizaciones</p>
        <h2 class="blog-hero-title">Todo lo que pasa en La Taberna</h2>
        <p class="blog-hero-text">Crónicas de torneos, consejos de las mesas y la voz de la comunidad en un solo lugar.</p>
        <div class="blog-hero-actions">
          <a class="btn btn-primary" href="#blog-posts">Explorar novedades</a>
          <a class="btn" href="#blog-history">Ver historial</a>
        </div>
      </div>
      <ul class="blog-hero-highlights" aria-label="Qué vas a encontrar">
        <li class="blog-hero-highlight">Crónicas frescas de nuestras jornadas</li>
        <li class="blog-hero-highlight">Tips para sacarle jugo a cada mesa</li>
        <li class="blog-hero-highlight">Historias y logros de la comunidad tabernera</li>
      </ul>
    </section>

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
        <section class="blog-cta" aria-label="Usá La Taberna como app">
          <div class="blog-cta-icon" aria-hidden="true">📲</div>
          <div class="blog-cta-body">
            <h2 class="blog-cta-title">Agregá La Taberna a tu pantalla de inicio</h2>
            <p class="blog-cta-text"><strong>Tip:</strong> instalá el sitio como app para recibir las novedades a un toque de distancia.</p>
            <ol class="blog-cta-steps">
              <li>Desde tu navegador, abrí el menú de opciones o compartir.</li>
              <li>Elegí «Agregar a pantalla de inicio» o «Instalar aplicación».</li>
              <li>Confirmá y abrí La Taberna desde el nuevo ícono para vivir la experiencia completa.</li>
            </ol>
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
              @if ($hasHeroImage)
                <figure class="blog-card-media">
                  <img src="{{ $post->hero_image_url }}" alt="" loading="lazy">
                </figure>
              @endif

              <div class="card-body">
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
