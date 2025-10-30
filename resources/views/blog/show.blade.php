@extends('layouts.app')

@section('title', $post->title)

@section('content')
  <article class="page container blog-post">
    <header class="page-head">
      <h1 class="page-title">{{ $post->title }}</h1>
      <p class="blog-card-meta">
        @php $publishedAt = $post->published_at?->timezone(config('app.timezone', 'UTC')); @endphp
        <span>Por {{ $post->author->name ?? 'Equipo de La Taberna' }}</span>
        @if ($publishedAt)
          <span>· {{ $publishedAt->translatedFormat('d \d\e F, Y H:i') }}</span>
        @endif
      </p>
    </header>

    <div class="blog-post-content">
      {!! nl2br(e($post->content)) !!}
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
    </footer>
  </article>
@endsection
