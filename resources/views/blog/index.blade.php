@extends('layouts.app')

@section('title', 'Blog')

@section('content')
  <div class="page container blog-list">
    <header class="page-head">
      <h1 class="page-title">Novedades</h1>
      <p class="page-subtitle">Enterate de lo último que sucede en la taberna.</p>
    </header>

    <div class="blog-grid">
      @forelse ($posts as $post)
        <article class="card blog-card">
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
@endsection
