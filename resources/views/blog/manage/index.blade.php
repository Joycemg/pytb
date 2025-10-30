@extends('layouts.app')

@section('title', 'Administrar blog')

@section('content')
  <div class="page container blog-manage">
    <header class="page-head">
      <h1 class="page-title">Administrar blog</h1>
      <a class="btn btn-primary" href="{{ route('blog.create') }}">+ Nueva entrada</a>
    </header>

    @if (session('status'))
      <div class="flash flash-success">{{ session('status') }}</div>
    @endif

    <div class="card">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>Título</th>
                <th>Autor</th>
                <th>Publicado</th>
                <th class="text-right">Acciones</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($posts as $post)
                <tr>
                  <td>
                    <a href="{{ route('blog.show', ['post' => $post->slug]) }}" target="_blank" rel="noopener">{{ $post->title }}</a>
                  </td>
                  <td>{{ $post->author->name ?? '—' }}</td>
                  <td>
                    @if ($post->published_at)
                      {{ $post->published_at->timezone(config('app.timezone', 'UTC'))->format('d/m/Y H:i') }}
                    @else
                      <span class="badge warn">Borrador</span>
                    @endif
                  </td>
                  <td class="text-right">
                    <div class="btn-group">
                      <a class="btn" href="{{ route('blog.edit', $post) }}">Editar</a>
                      <form method="post" action="{{ route('blog.destroy', $post) }}" onsubmit="return confirm('¿Eliminar entrada? Esta acción no se puede deshacer.');">
                        @csrf
                        @method('delete')
                        <button class="btn btn-danger" type="submit">Eliminar</button>
                      </form>
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="4">No hay entradas todavía.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <div class="blog-pagination">
          {{ $posts->links() }}
        </div>
      </div>
    </div>
  </div>
@endsection
