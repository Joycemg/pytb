@extends('layouts.app')

@section('title', $post->title)

@section('content')
  @php
    $theme = $post->theme ?? config('blog.default_theme', 'classic');
    $themes = (array) config('blog.themes', []);
    if (!array_key_exists($theme, $themes)) {
        $theme = config('blog.default_theme', 'classic');
    }
    $accent = $post->accent_color ?? ($themes[$theme]['accent'] ?? config('blog.default_accent'));
    $accentText = $post->accent_text_color ?? ($themes[$theme]['text'] ?? config('blog.default_text_color'));

    $rawContent = $post->content ?? '';
    $plainContent = trim(preg_replace('/\s+/', ' ', strip_tags((string) $rawContent)) ?? '');
    $wordCount = max(0, str_word_count($plainContent));
    $readingMinutes = max(1, (int) ceil($wordCount / 220));
  @endphp

  <article class="page container blog-post blog-theme-{{ $theme }}" style="--blog-accent: {{ $accent }}; --blog-accent-text: {{ $accentText }};">
    <header class="page-head blog-post-head">
      <a class="blog-post-back" href="{{ route('blog.index') }}">← Volver a las novedades</a>
      <p class="blog-post-eyebrow">Publicación destacada</p>
      <h1 class="page-title">{{ $post->title }}</h1>
      <div class="blog-post-meta">
        @php $publishedAt = $post->published_at?->timezone(config('app.timezone', 'UTC')); @endphp
        <p class="blog-card-meta">
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

        <div class="blog-post-actions">
          <button type="button"
                  class="blog-post-share"
                  data-copy-url="{{ route('blog.show', ['post' => $post->slug]) }}"
                  data-label-default="Copiar enlace"
                  data-label-copied="Enlace copiado">
            <span aria-hidden="true">🔗</span>
            <span class="blog-post-share-text">Copiar enlace</span>
          </button>
          <a class="blog-post-history" href="{{ route('blog.index') }}#blog-history">Ver historial</a>
          <span class="sr-only" data-copy-feedback aria-live="polite"></span>
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
        <img src="{{ $post->hero_image_url }}" alt="{{ $post->hero_image_caption ?? '' }}">
        @if ($post->hero_image_caption)
          <figcaption>{{ $post->hero_image_caption }}</figcaption>
        @endif
      </figure>
    @endif

    @if (filled($post->excerpt))
      <p class="blog-post-lede">{{ $post->excerpt }}</p>
    @endif

    @php
      $rawContent = $post->content ?? '';
      $hasMarkup = str_contains((string) $rawContent, '<');
    @endphp
    <div class="blog-post-content">
      {!! $hasMarkup ? $rawContent : nl2br(e($rawContent)) !!}
    </div>

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
      <a class="btn btn-primary" href="{{ route('blog.rss') }}">Suscribirme al RSS</a>
    </footer>
  </article>
@endsection

@push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const buttons = document.querySelectorAll('[data-copy-url]');

      buttons.forEach(function (button) {
        const defaultLabel = button.getAttribute('data-label-default') || button.textContent.trim();
        const copiedLabel = button.getAttribute('data-label-copied') || 'Copiado';
        const feedback = button.parentElement?.querySelector('[data-copy-feedback]');

        button.addEventListener('click', async function () {
          const url = button.getAttribute('data-copy-url');
          if (!url) {
            return;
          }

          let copied = false;
          if (navigator.clipboard && navigator.clipboard.writeText) {
            try {
              await navigator.clipboard.writeText(url);
              copied = true;
            } catch (error) {
              copied = false;
            }
          }

          if (!copied) {
            const textarea = document.createElement('textarea');
            textarea.value = url;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'absolute';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();
            try {
              document.execCommand('copy');
              copied = true;
            } catch (error) {
              copied = false;
            }
            document.body.removeChild(textarea);
          }

          if (copied) {
            button.classList.add('is-copied');
            const labelTarget = button.querySelector('.blog-post-share-text');
            if (labelTarget) {
              labelTarget.textContent = copiedLabel;
            } else {
              button.textContent = copiedLabel;
            }

            if (feedback) {
              feedback.textContent = 'Enlace copiado al portapapeles';
            }

            window.setTimeout(function () {
              button.classList.remove('is-copied');
              if (labelTarget) {
                labelTarget.textContent = defaultLabel;
              } else {
                button.textContent = defaultLabel;
              }
              if (feedback) {
                feedback.textContent = '';
              }
            }, 2800);
          }
        });
      });
    });
  </script>
@endpush
