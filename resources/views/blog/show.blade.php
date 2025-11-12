@extends('layouts.app')

@section('title', $post->title)

@push('head')
  <link rel="stylesheet" href="{{ asset('css/blog/blog.tokens.css') }}">
  <link rel="stylesheet" href="{{ asset('css/blog/blog.layout.css') }}">
  <link rel="stylesheet" href="{{ asset('css/blog/blog.post-customizer-responsive.css') }}">
  <link rel="stylesheet" href="{{ asset('css/blog/components.css') }}">
@endpush

@section('content')
  @php
    $themes = (array) config('blog.themes', []);
    $defaultTheme = config('blog.default_theme', 'classic');
    $theme = ($post->theme && array_key_exists($post->theme, $themes)) ? $post->theme : $defaultTheme;

    $accent = $post->accent_color ?? ($themes[$theme]['accent'] ?? config('blog.default_accent'));
    $accentText = $post->accent_text_color ?? ($themes[$theme]['text'] ?? config('blog.default_text_color'));
    $accentRgb = null;
    if (is_string($accent) && preg_match('/^#?([0-9a-f]{6})$/i', $accent, $accentMatches)) {
      $hexColor = $accentMatches[1];
      $accentRgb = [
        hexdec(substr($hexColor, 0, 2)),
        hexdec(substr($hexColor, 2, 2)),
        hexdec(substr($hexColor, 4, 2)),
      ];
    }

    $rawContent = $post->content ?? '';
    $plainContent = trim(preg_replace('/\s+/', ' ', strip_tags((string) $rawContent)) ?? '');
    $wordsPerMinute = max(80, (int) config('blog.words_per_minute', 220));
    $wordCount = max(0, str_word_count($plainContent));
    $readingMinutes = max(1, (int) ceil($wordCount / $wordsPerMinute));

    $likesSummary = array_merge([
      'count' => 0,
      'hasLiked' => false,
    ], $likesSummary ?? []);

    $comments = collect($comments ?? []);
    $commentsCount = $comments->count();
    $userComment = $userComment ?? null;
    $canComment = $canComment ?? false;
    $canLike = $canLike ?? false;
    $timezone = config('app.timezone', 'UTC');
    $heroImageAlt = trim($post->hero_image_caption ?? '') ?: ($post->title ?? 'Imagen del artículo');
  @endphp

    @php $accentRgbString = $accentRgb ? implode(', ', $accentRgb) : null; @endphp
  <article class="page container blog-post blog-theme-{{ $theme }}" style="--blog-accent: {{ $accent }}; --blog-accent-text: {{ $accentText }}; @if($accentRgbString) --blog-accent-rgb: {{ $accentRgbString }}; @endif">
    <header class="page-head blog-post-head">
      <div class="blog-post-head-top">
        <a class="blog-post-back" href="{{ route('blog.index') }}">← Volver a las novedades</a>
        @php $isFeatured = (bool) ($post->is_featured ?? false); @endphp
        @if ($isFeatured)
          <p class="blog-post-eyebrow">Publicación destacada</p>
        @endif
        <h1 class="page-title blog-post-title">{{ $post->title }}</h1>
      </div>

      <div class="blog-post-meta-card" role="list">
        @php $publishedAt = $post->published_at?->timezone($timezone); @endphp
        <p class="blog-post-meta" role="listitem">
          @php $authorName = trim($post->author->name ?? ''); @endphp
          <span class="blog-post-meta-item">
            <span class="blog-post-meta-icon" aria-hidden="true">✍️</span>
            <span>Por {{ $authorName !== '' ? $authorName : 'Equipo de La Taberna' }}</span>
          </span>
          @if ($publishedAt)
            <span class="blog-post-meta-divider" role="presentation"></span>
            <span class="blog-post-meta-item">
              <span class="blog-post-meta-icon" aria-hidden="true">🗓️</span>
              <time datetime="{{ $publishedAt->toIso8601String() }}">{{ $publishedAt->translatedFormat('d \d\e F, Y H:i') }}</time>
            </span>
          @endif
          @if ($wordCount > 0)
            <span class="blog-post-meta-divider" role="presentation"></span>
            <span class="blog-post-meta-item">
              <span class="blog-post-meta-icon" aria-hidden="true">⏱️</span>
              <span>{{ $readingMinutes }} {{ \Illuminate\Support\Str::plural('minuto', $readingMinutes) }} de lectura</span>
            </span>
          @endif
        </p>

        <div class="blog-post-actions" role="listitem">
          <a class="blog-post-history" href="{{ route('blog.index') }}#blog-history">Ver historial</a>
        </div>
      </div>

    </header>

    @if ($post->tags->isNotEmpty())
      <ul class="blog-post-tags" aria-label="Etiquetas">
        @foreach ($post->tags as $tag)
          <li>
            <a class="blog-post-tag" href="{{ route('blog.index', ['q' => '#' . $tag->name]) }}">
              <span class="blog-post-tag-symbol" aria-hidden="true">#</span>
              <span>{{ $tag->name }}</span>
            </a>
          </li>
        @endforeach
      </ul>
    @endif

    @if ($post->hero_image_url)
      <figure class="blog-post-hero">
        <img
          src="{{ $post->hero_image_url }}"
          alt="{{ $heroImageAlt }}"
          loading="lazy"
          decoding="async"
        >
        @if ($post->hero_image_caption)
          <figcaption>{{ $post->hero_image_caption }}</figcaption>
        @endif
      </figure>
    @endif

    @php
      $rawContent = $post->content ?? '';
      $hasMarkup = str_contains((string) $rawContent, '<');
    @endphp
    <div class="blog-post-content">
      {!! $hasMarkup ? $rawContent : nl2br(e($rawContent)) !!}
    </div>

    @php
      $likesCount = (int) $likesSummary['count'];
      $hasLiked = (bool) $likesSummary['hasLiked'];
    @endphp
    <section class="blog-post-likes" role="region" aria-live="polite">
      <div class="blog-post-likes-info">
        <span class="blog-post-likes-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false">
            <path d="M9.75 21.75a1.5 1.5 0 0 1-1.5-1.5v-6a.75.75 0 0 0-.75-.75H4.5a1.5 1.5 0 0 0-1.5 1.5v6a1.5 1.5 0 0 0 1.5 1.5h5.25Zm2.222-.023c-1.074 0-1.947-.873-1.947-1.947V9.92c0-.525.207-1.029.574-1.4l4.58-4.611a1.1 1.1 0 0 1 1.873.779v2.98h3.274a1.5 1.5 0 0 1 1.447 1.911l-2.037 7.012a2.25 2.25 0 0 1-2.158 1.611h-5.606Z" fill="currentColor" />
          </svg>
        </span>
        <p class="blog-post-likes-count">
          @if ($likesCount === 0)
            Sé la primera persona en marcar “Me gusta”.
          @else
            <strong>{{ $likesCount }}</strong>
            {{ \Illuminate\Support\Str::plural('persona', $likesCount) }}
            {{ $likesCount === 1 ? 'marcó' : 'marcaron' }} “Me gusta”.
          @endif
        </p>
      </div>
      <div class="blog-post-likes-action">
        @auth
          @if ($canLike)
            <form method="post" action="{{ route('blog.likes.toggle', $post) }}">
              @csrf
              <button type="submit" class="blog-post-like-button{{ $hasLiked ? ' is-active' : '' }}" data-once>
                <span class="blog-post-like-button-icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false">
                    <path d="M9.75 21.75a1.5 1.5 0 0 1-1.5-1.5v-6a.75.75 0 0 0-.75-.75H4.5a1.5 1.5 0 0 0-1.5 1.5v6a1.5 1.5 0 0 0 1.5 1.5h5.25Zm2.222-.023c-1.074 0-1.947-.873-1.947-1.947V9.92c0-.525.207-1.029.574-1.4l4.58-4.611a1.1 1.1 0 0 1 1.873.779v2.98h3.274a1.5 1.5 0 0 1 1.447 1.911l-2.037 7.012a2.25 2.25 0 0 1-2.158 1.611h-5.606Z" fill="currentColor" />
                  </svg>
                </span>
                <span class="blog-post-like-button-label">{{ $hasLiked ? 'Quitar “Me gusta”' : '¡Me gusta!' }}</span>
              </button>
            </form>
          @else
            <p class="blog-post-likes-hint">Tu cuenta debe estar aprobada para marcar “Me gusta”.</p>
          @endif
        @else
          <a class="blog-post-likes-login" href="{{ route('auth.login') }}">Ingresá para marcar “Me gusta”.</a>
        @endauth
      </div>
    </section>

    <section class="blog-comments" id="comentarios" aria-labelledby="blog-comments-title">
      <div class="blog-comments-head">
        <h2 id="blog-comments-title">Comentarios</h2>
        <span class="blog-comments-count" aria-label="Cantidad de comentarios">{{ $commentsCount }}</span>
      </div>

      @auth
        @if ($canComment)
          @php
            $commentBody = old('body', optional($userComment)->body);
          @endphp
          <form method="post" action="{{ route('blog.comments.store', $post) }}" class="blog-comment-form">
            @csrf

            <div class="blog-comment-form-field">
              <label for="comment-body" class="blog-comment-label">Tu comentario</label>
              <textarea id="comment-body" name="body" rows="4" required>{{ $commentBody }}</textarea>
              <p class="blog-comment-hint">Contanos qué te pareció esta publicación.</p>
            </div>

            @if ($userComment)
              <p class="blog-comment-note">Ya dejaste tu comentario. Podés actualizarlo cuando quieras.</p>
            @endif

            <button type="submit" class="btn btn-primary" data-once>Guardar mi comentario</button>
          </form>
        @else
          <p class="blog-comments-hint">Tu cuenta debe estar aprobada para poder comentar y reaccionar a las publicaciones.</p>
        @endif
      @else
        <p class="blog-comments-hint">
          <a href="{{ route('auth.login') }}">Ingresá</a> para dejar tu comentario y marcar “Me gusta”.
        </p>
      @endauth

      @if ($commentsCount > 0)
        <ul class="blog-comments-list">
          @foreach ($comments as $comment)
            @php
              $commentAt = optional($comment->created_at)?->timezone($timezone);
              $isSelf = $userComment && $comment->id === $userComment->id;
            @endphp
            <li class="blog-comment{{ $isSelf ? ' is-self' : '' }}">
              <div class="blog-comment-header">
                <div class="blog-comment-author">
                  <span class="blog-comment-name">{{ $comment->author->name ?? 'Miembro de la comunidad' }}</span>
                  @if ($isSelf)
                    <span class="blog-comment-badge">Tu comentario</span>
                  @endif
                </div>
                <div class="blog-comment-meta">
                  @if ($commentAt)
                    <time datetime="{{ $commentAt->toIso8601String() }}">{{ $commentAt->diffForHumans() }}</time>
                  @endif
                </div>
              </div>
              <p class="blog-comment-body">{!! nl2br(e($comment->body)) !!}</p>
            </li>
          @endforeach
        </ul>
      @else
        <p class="blog-comments-empty">Todavía no hay comentarios. ¡Sé la primera persona en opinar!</p>
      @endif
    </section>

    @if ($post->attachments->isNotEmpty())
      <section class="blog-post-attachments">
        <h2>Archivos adjuntos</h2>
        <ul class="blog-post-attachments-list">
          @foreach ($post->attachments as $attachment)
            <li>
              <a
                href="{{ Storage::disk('public')->url($attachment->path) }}"
                target="_blank"
                rel="noopener"
                class="blog-post-attachment-link"
              >
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

