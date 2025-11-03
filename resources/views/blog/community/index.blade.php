@extends('layouts.app')

@section('title', 'Crónicas del club')

@push('head')
  <link rel="stylesheet" href="/css/blog-history.css">
  <link rel="stylesheet" href="/css/blog-hero-filter.css">
@endpush

@section('content')
  <div class="page container blog-community-page">
    <header class="page-head blog-community-hero">
      <div>
        <p class="blog-community-eyebrow">Crónicas del club</p>
        <h1 class="page-title">Aportes del club</h1>
        <p class="blog-community-subtitle">Un espacio para que las personas de la Taberna compartan ideas, recomendaciones y experiencias.</p>
      </div>

      <div class="blog-community-hero-actions">
        <a class="btn" href="{{ route('blog.index') }}">← Volver al blog</a>
        @if ($canSubmit)
          <a class="btn btn-primary" href="{{ route('blog.community.create') }}">Crear mi aporte</a>
        @else
          <a class="btn btn-primary" href="{{ route('login') }}">Ingresar para participar</a>
        @endif
      </div>
    </header>

    <section class="blog-community-search" aria-label="Buscar en Crónicas del club">
      <form method="get" action="{{ route('blog.community') }}" class="blog-hero-search" role="search">
        <div class="blog-filter-field blog-filter-field--search">
          <label for="community-search">Buscá por título, etiqueta o autor</label>
          <div class="blog-filter-input">
            <input id="community-search" type="search" name="q" value="{{ $filters['input']['q'] ?? '' }}" autocomplete="off" class="blog-filter-input-control">
            <button type="submit" class="blog-filter-input-action" aria-label="Buscar">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="blog-filter-input-icon">
                <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 1-3.478 9.756l-2.384 2.384a.75.75 0 0 1-1.06-1.06l2.384-2.384A5.5 5.5 0 0 1 9 3.5Zm0 1.5a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z" clip-rule="evenodd" />
              </svg>
            </button>
          </div>
        </div>

        @if (!empty($filters['active']))
          <div class="blog-filter-actions">
            <a class="blog-filter-reset" href="{{ route('blog.community') }}">Limpiar</a>
          </div>
        @endif
      </form>

      @if (!empty($filters['active']) && filled($filters['applied']['search'] ?? ''))
        <p class="blog-filter-active" role="status" aria-live="polite">
          Mostrando crónicas para <strong>{{ $filters['applied']['search'] }}</strong>
          <a class="blog-filter-reset" href="{{ route('blog.community') }}">Quitar filtro</a>
        </p>
      @endif
    </section>

    <section class="blog-community-feed" aria-live="polite">
      <ul class="post-list" itemscope itemtype="https://schema.org/Blog">
        @forelse ($posts as $post)
          @php
            $publishedAt = $post->published_at?->timezone(config('app.timezone', 'UTC'));
            $author = $post->author->name ?? 'Autor del club';
          @endphp
          <li class="post-row" itemprop="blogPost" itemscope itemtype="https://schema.org/BlogPosting">
            <div class="post-row-left">
              <a href="{{ route('blog.show', ['post' => $post->slug]) }}" class="post-row-title" itemprop="headline">
                {{ $post->title }}
              </a>

              <div class="post-row-meta">
                <span class="post-row-badge">Crónicas del club</span>
                <span class="post-row-sep">·</span>
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
                    <li><a class="post-row-tag" href="{{ route('blog.community', ['q' => '#' . $tag->name]) }}">#{{ $tag->name }}</a></li>
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
            @if ($canSubmit)
              Aún no hay aportes publicados. ¡Animate a compartir el tuyo!
            @else
              Todavía no hay aportes disponibles.
            @endif
          </li>
        @endforelse
      </ul>

      <div class="blog-pagination">
        {{ $posts->links() }}
      </div>
    </section>
  </div>
@endsection
