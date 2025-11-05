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

    $rawContent = $post->content ?? '';
    $plainContent = trim(preg_replace('/\s+/', ' ', strip_tags((string) $rawContent)) ?? '');
    $wordsPerMinute = max(80, (int) config('blog.words_per_minute', 220));
    $wordCount = max(0, str_word_count($plainContent));
    $readingMinutes = max(1, (int) ceil($wordCount / $wordsPerMinute));

    $ratingSummary = array_merge([
      'average' => 0.0,
      'count' => 0,
      'full' => 0,
      'partial' => 0.0,
    ], $ratingSummary ?? []);

    $comments = collect($comments ?? []);
    $commentsCount = $comments->count();
    $userComment = $userComment ?? null;
    $canComment = $canComment ?? false;
    $timezone = config('app.timezone', 'UTC');
    $heroImageAlt = trim($post->hero_image_caption ?? '') ?: ($post->title ?? 'Imagen del artículo');
  @endphp

  <article class="page container blog-post blog-theme-{{ $theme }}" style="--blog-accent: {{ $accent }}; --blog-accent-text: {{ $accentText }};">
    <header class="page-head blog-post-head">
      <div class="blog-post-head-top">
        <a class="blog-post-back" href="{{ route('blog.index') }}">← Volver a las novedades</a>
        <p class="blog-post-eyebrow">Publicación destacada</p>
        <h1 class="page-title">{{ $post->title }}</h1>
      </div>

      <div class="blog-post-meta" role="list">
        @php $publishedAt = $post->published_at?->timezone($timezone); @endphp
        <p class="blog-card-meta" role="listitem">
          <span>Por {{ $post->author->name ?? 'Equipo de La Taberna' }}</span>
          @if ($publishedAt)
            <span aria-hidden="true" class="blog-post-meta-separator">•</span>
            <time datetime="{{ $publishedAt->toIso8601String() }}">{{ $publishedAt->translatedFormat('d \d\e F, Y H:i') }}</time>
          @endif
          @if ($wordCount > 0)
            <span aria-hidden="true" class="blog-post-meta-separator">•</span>
            <span>{{ $readingMinutes }} {{ \Illuminate\Support\Str::plural('minuto', $readingMinutes) }} de lectura</span>
          @endif
        </p>

        <div class="blog-post-actions" role="listitem">
          <a class="blog-post-history" href="{{ route('blog.index') }}#blog-history">Ver historial</a>
        </div>
      </div>

      @php
        $fullMeeples = (int) $ratingSummary['full'];
        $partialMeeple = (float) $ratingSummary['partial'];
        $averageLabel = number_format($ratingSummary['average'], 1);
        $ratingsCount = (int) $ratingSummary['count'];
      @endphp
      <div class="blog-post-rating" role="region" aria-live="polite">
        <div class="blog-post-rating-summary" aria-label="Calificación promedio de la comunidad">
          <div class="meeple-rating meeple-rating--lg" role="img" aria-label="{{ $ratingsCount > 0 ? $averageLabel . ' de 5 meeples' : 'Sin calificaciones todavía' }}">
            @for ($i = 1; $i <= 5; $i++)
              @php
                $isFull = $i <= $fullMeeples;
                $isPartial = !$isFull && $i === $fullMeeples + 1 && $partialMeeple > 0;
              @endphp
              <span class="meeple-rating-icon{{ $isFull ? ' is-active' : '' }}{{ $isPartial ? ' is-partial' : '' }}"
                    @if ($isPartial) style="--meeple-fill: {{ max(0, min(100, round($partialMeeple * 100))) }}%;" @endif></span>
            @endfor
          </div>
          <div class="blog-post-rating-data">
            <span class="blog-post-rating-average">{{ $ratingsCount > 0 ? $averageLabel : '—' }}</span>
            <span class="blog-post-rating-count">
              @if ($ratingsCount > 0)
                {{ $ratingsCount }} {{ \Illuminate\Support\Str::plural('comentario', $ratingsCount) }}
              @else
                Sin calificaciones todavía
              @endif
            </span>
          </div>
        </div>
      </div>
    </header>

    @if ($post->tags->isNotEmpty())
      <ul class="blog-post-tags" aria-label="Etiquetas">
        @foreach ($post->tags as $tag)
          <li><a class="blog-post-tag" href="{{ route('blog.index', ['q' => '#' . $tag->name]) }}">#{{ $tag->name }}</a></li>
        @endforeach
      </ul>
    @endif

    @if ($post->hero_image_url)
      <figure class="blog-post-hero">
        <img src="{{ $post->hero_image_url }}" alt="{{ $heroImageAlt }}">
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

    <section class="blog-comments" id="comentarios" aria-labelledby="blog-comments-title">
      <div class="blog-comments-head">
        <h2 id="blog-comments-title">Comentarios</h2>
        <span class="blog-comments-count" aria-label="Cantidad de comentarios">{{ $commentsCount }}</span>
      </div>

      @auth
        @if ($canComment)
          @php
            $currentRating = (int) old('rating', optional($userComment)->rating);
            if ($currentRating < 1 || $currentRating > 5) {
              $currentRating = 0;
            }
            $commentBody = old('body', optional($userComment)->body);
          @endphp
          <form method="post" action="{{ route('blog.comments.store', $post) }}" class="blog-comment-form">
            @csrf

            <div class="blog-comment-form-rating">
              <span id="comment-rating-label" class="blog-comment-label">Tu calificación (1 a 5 meeples)</span>
              <div class="meeple-rating-input" role="radiogroup" aria-labelledby="comment-rating-label">
                @for ($i = 1; $i <= 5; $i++)
                  <input type="radio"
                         id="comment-rating-{{ $i }}"
                         name="rating"
                         value="{{ $i }}"
                         {{ $currentRating === $i ? 'checked' : '' }}
                         {{ $i === 1 ? 'required' : '' }}>
                  <label for="comment-rating-{{ $i }}" class="meeple-rating-input-label">
                    <span class="sr-only">{{ $i }} {{ \Illuminate\Support\Str::plural('meeple', $i) }}</span>
                    <span class="meeple-rating-icon" aria-hidden="true"></span>
                  </label>
                @endfor
              </div>
            </div>

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
          <p class="blog-comments-hint">Tu cuenta debe estar aprobada para poder comentar y puntuar publicaciones.</p>
        @endif
      @else
        <p class="blog-comments-hint">
          <a href="{{ route('auth.login') }}">Ingresá</a> para dejar tu comentario y sumar meeples.
        </p>
      @endauth

      @if ($commentsCount > 0)
        <ul class="blog-comments-list">
          @foreach ($comments as $comment)
            @php
              $commentAt = optional($comment->created_at)?->timezone($timezone);
              $commentRating = (int) ($comment->rating ?? 0);
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
                  @if ($commentRating > 0)
                    <div class="meeple-rating meeple-rating--sm" role="img" aria-label="{{ $commentRating }} de 5 meeples">
                      @for ($i = 1; $i <= 5; $i++)
                        <span class="meeple-rating-icon{{ $i <= $commentRating ? ' is-active' : '' }}"></span>
                      @endfor
                    </div>
                  @endif
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
        <ul>
          @foreach ($post->attachments as $attachment)
            <li>
              <a href="{{ Storage::disk('public')->url($attachment->path) }}" target="_blank" rel="noopener">
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

