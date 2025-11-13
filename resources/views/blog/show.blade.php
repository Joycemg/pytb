@extends('layouts.app')

@section('title', $post->title)

@push('head')
  <link rel="stylesheet"
        href="{{ asset('css/blog/blog.tokens.css') }}">
  <link rel="stylesheet"
        href="{{ asset('css/blog/blog.layout.css') }}">
  <link rel="stylesheet"
        href="{{ asset('css/blog/blog.post-customizer-responsive.css') }}">
  <link rel="stylesheet"
        href="{{ asset('css/blog/components.css') }}">
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

  @php
    $accentRgbString = $accentRgb ? implode(', ', $accentRgb) : null;
    $themeStyleTokens = [
      "--blog-accent: {$accent};",
      "--blog-accent-text: {$accentText};",
    ];
    if ($accentRgbString) {
      $themeStyleTokens[] = "--blog-accent-rgb: {$accentRgbString};";
    }
    $themeStyleAttr = implode(' ', $themeStyleTokens);
  @endphp
  <article class="page container blog-post blog-theme-{{ $theme }}"
           style="{{ $themeStyleAttr }}">
    <header class="page-head blog-post-head">
      <div class="blog-post-head-top">
        <a class="blog-post-back"
           href="{{ route('blog.index') }}">← Volver a las novedades</a>
        @php $isFeatured = (bool) ($post->is_featured ?? false); @endphp
        @if ($isFeatured)
          <p class="blog-post-eyebrow">Publicación destacada</p>
        @endif
        <h1 class="page-title blog-post-title">{{ $post->title }}</h1>
        <p class="blog-post-meta"
           role="list">
          @php $authorName = trim($post->author->name ?? ''); @endphp
          <span class="blog-post-meta-item"
                role="listitem">
            <span class="blog-post-meta-icon"
                  aria-hidden="true"></span>
            <span>Por {{ $authorName !== '' ? $authorName : 'Equipo de La Taberna' }}</span>
          </span>
          @php $publishedAt = $post->published_at?->timezone($timezone); @endphp
          @if ($publishedAt)
            <span class="blog-post-meta-divider"
                  role="presentation"></span>
            <span class="blog-post-meta-item"
                  role="listitem">
              <span class="blog-post-meta-icon"
                    aria-hidden="true"></span>
              <time
                    datetime="{{ $publishedAt->toIso8601String() }}">{{ $publishedAt->translatedFormat('d \d\e F, Y H:i') }}</time>
            </span>
          @endif
          @if ($wordCount > 0)
            <span class="blog-post-meta-divider"
                  role="presentation"></span>
            <span class="blog-post-meta-item"
                  role="listitem">
              <span class="blog-post-meta-icon"></span>
            </span>
          @endif
        </p>
      </div>

      <div class="blog-post-meta-card">
        <div class="blog-post-actions">

        </div>
      </div>

    </header>

    @if ($post->hero_image_url)
      <figure class="blog-post-hero">
        <img src="{{ $post->hero_image_url }}"
             alt="{{ $heroImageAlt }}"
             loading="lazy"
             decoding="async">
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
    @php
      $likesSummaryText = $likesCount === 0
        ? 'Sé la primera persona en marcar “Me gusta”.'
        : sprintf(
          '%d %s %s “Me gusta”.',
          $likesCount,
          \Illuminate\Support\Str::plural('persona', $likesCount),
          $likesCount === 1 ? 'marcó' : 'marcaron'
        );
      $likeButtonLabel = $hasLiked ? 'Quitar “Me gusta”' : 'Marcar “Me gusta”';
      $likeButtonAria = $likesCount > 0
        ? sprintf('%s. %s', $likeButtonLabel, $likesSummaryText)
        : $likeButtonLabel;
    @endphp
    <section class="blog-post-likes"
             role="region"
             aria-live="polite">
      <div class="blog-post-likes-main">
        @auth
          @if ($canLike)
            <form method="post"
                  action="{{ route('blog.likes.toggle', $post) }}"
                  class="blog-post-like-form">
              @csrf
              <button type="submit"
                      class="blog-post-like-button{{ $hasLiked ? ' is-active' : '' }}"
                      data-once
                      data-label-pending="{{ $hasLiked ? 'Te gusta' : 'Me gusta' }}"
                      aria-pressed="{{ $hasLiked ? 'true' : 'false' }}"
                      aria-label="{{ $likeButtonAria }}">
                <span class="blog-post-like-button-icon"
                      aria-hidden="true">
                  <svg viewBox="0 0 24 24"
                       role="img"
                       aria-hidden="true"
                       focusable="false">
                    <path d="M9.75 21.75a1.5 1.5 0 0 1-1.5-1.5v-6a.75.75 0 0 0-.75-.75H4.5a1.5 1.5 0 0 0-1.5 1.5v6a1.5 1.5 0 0 0 1.5 1.5h5.25Zm2.222-.023c-1.074 0-1.947-.873-1.947-1.947V9.92c0-.525.207-1.029.574-1.4l4.58-4.611a1.1 1.1 0 0 1 1.873.779v2.98h3.274a1.5 1.5 0 0 1 1.447 1.911l-2.037 7.012a2.25 2.25 0 0 1-2.158 1.611h-5.606Z"
                          fill="currentColor" />
                  </svg>
                </span>
                <span class="blog-post-like-button-label">{{ $hasLiked ? 'Te gusta' : 'Me gusta' }}</span>
                <span class="blog-post-like-button-count"
                      aria-hidden="true">{{ number_format($likesCount, 0, ',', '.') }}</span>
                <span class="sr-only">{{ $likesSummaryText }}</span>
              </button>
            </form>
          @else

          @endif
        @else

        @endauth
      </div>
    </section>

    @if ($post->tags->isNotEmpty())
      <ul class="blog-post-tags"
          aria-label="Etiquetas">
        @foreach ($post->tags as $tag)
          <li>
            <a class="blog-post-tag"
               href="{{ route('blog.index', ['q' => '#' . $tag->name]) }}">
              <span class="blog-post-tag-symbol"
                    aria-hidden="true">#</span>
              <span>{{ $tag->name }}</span>
            </a>
          </li>
        @endforeach
      </ul>
    @endif

  </article>

  <section class="page container blog-comments-section"
           id="comentarios"
           aria-labelledby="blog-comments-title">
    @php
      $commentsCountFormatted = number_format($commentsCount, 0, ',', '.');
      $commentsCountLabel = $commentsCount === 1 ? $commentsCountFormatted . ' comentario' : $commentsCountFormatted . ' comentarios';
      $commentsSummary = $commentsCount > 0
          ? 'Esto es lo que dice nuestra comunidad.'
          : 'Todavía no hay comentarios, ¡sumate a la conversación!';
      $commentsEmptyMessage = 'Todavía no hay comentarios. ¡Sé la primera persona en opinar!';
    @endphp
    <div class="blog-comments"
         style="{{ $themeStyleAttr }}">
      <div class="blog-comments-head">
        <div class="blog-comments-heading">
          <h2 id="blog-comments-title"
              class="blog-comments-title">Comentarios</h2>
          <p class="blog-comments-summary">{{ $commentsSummary }}</p>
        </div>
        <div class="blog-comments-count"
             role="status"
             aria-live="polite">
          <span aria-hidden="true">{{ $commentsCountFormatted }}</span>
          <span class="sr-only">{{ $commentsCountLabel }}</span>
        </div>
      </div>

      <div class="blog-comments-body">
        <div class="blog-comments-column blog-comments-column-form">
          @auth
            @if ($canComment)
              @if ($userComment === null)
                @php
                  $commentBody = old('body', '');
                @endphp
                <form method="post"
                      action="{{ route('blog.comments.store', $post) }}"
                      class="blog-comment-form">
                  @csrf

                  <div class="blog-comment-form-field">
                    <label for="comment-body"
                           class="blog-comment-label">Tu comentario</label>
                    <textarea id="comment-body"
                              name="body"
                              rows="4"
                              required>{{ $commentBody }}</textarea>
                    <p class="blog-comment-hint">Contanos qué te pareció esta publicación.</p>
                  </div>

                  <button type="submit"
                          class="btn btn-primary"
                          data-once>Guardar mi comentario</button>
                </form>
              @else
                <p class="blog-comment-note">Ya dejaste tu comentario.</p>
              @endif
            @else
              <p class="blog-comments-hint">Tu cuenta debe estar aprobada para poder comentar y reaccionar a las publicaciones.</p>
            @endif
          @else
            <p class="blog-comments-hint">
              <a href="{{ route('auth.login') }}">Ingresá</a> para dejar tu comentario.
            </p>
          @endauth
        </div>

          <div class="blog-comments-column blog-comments-column-list">
            @if ($commentsCount > 0)
              <ul class="blog-comments-list">
                @foreach ($comments as $comment)
                  @php
                    $commentAt = optional($comment->created_at)?->timezone($timezone);
                    $isSelf = $userComment && $comment->id === $userComment->id;
                    $authorName = $comment->author->name ?? 'Miembro de la comunidad';
                    $authorInitial = mb_strtoupper(mb_substr($authorName, 0, 1, 'UTF-8'), 'UTF-8');
                    if ($authorInitial === '') {
                      $authorInitial = '?';
                    }
                  @endphp
                  <li class="blog-comment{{ $isSelf ? ' is-self' : '' }}">
                    <div class="blog-comment-avatar"
                         aria-hidden="true">{{ $authorInitial }}</div>
                    <div class="blog-comment-content">
                      <div class="blog-comment-header">
                        <div class="blog-comment-author">
                          <span class="blog-comment-name">{{ $authorName }}</span>
                          @if ($isSelf)
                            <span class="blog-comment-badge">Tu comentario</span>
                          @endif
                        </div>
                        <div class="blog-comment-meta">
                          @if ($commentAt)
                            <time datetime="{{ $commentAt->toIso8601String() }}"
                                  class="blog-comment-timestamp">{{ $commentAt->diffForHumans() }}</time>
                          @endif
                          @if (auth()->user()?->hasAnyRole(['admin', 'moderator']))
                            <form method="post"
                                  action="{{ route('blog.comments.destroy', [$post, $comment]) }}"
                                  class="blog-comment-delete-form"
                                  onsubmit="return confirm('¿Eliminar este comentario?');">
                              @csrf
                              @method('delete')
                              <button type="submit"
                                      class="blog-comment-delete-button">
                                Eliminar
                              </button>
                            </form>
                          @endif
                        </div>
                      </div>
                      <p class="blog-comment-body">{!! nl2br(e($comment->body)) !!}</p>
                    </div>
                  </li>
                @endforeach
              </ul>
            @else
              <p class="blog-comments-empty">{{ $commentsEmptyMessage }}</p>
            @endif
          </div>
      </div>
    </div>
  </section>

  @if ($post->attachments->isNotEmpty())
    <div class="page container blog-post-attachments-container"
         style="{{ $themeStyleAttr }}">
      <section class="blog-post-attachments">
        <h2>Archivos adjuntos</h2>
        <ul class="blog-post-attachments-list">
          @foreach ($post->attachments as $attachment)
            <li>
              <a href="{{ Storage::disk('public')->url($attachment->path) }}"
                 target="_blank"
                 rel="noopener"
                 class="blog-post-attachment-link">
                {{ $attachment->original_name }}
              </a>
              <span class="attachment-meta">({{ number_format($attachment->size / 1024, 1) }} KB)</span>
            </li>
          @endforeach
        </ul>
      </section>
    </div>
  @endif

  <div class="page container blog-post-footer-container"
       style="{{ $themeStyleAttr }}">
    <footer class="blog-post-footer">
      <a class="btn"
         href="{{ route('blog.index') }}">← Volver</a>
    </footer>
  </div>

@endsection
