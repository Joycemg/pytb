@extends('layouts.app')

@section('title', 'Mis aportes')

@push('head')
  <link rel="stylesheet" href="/css/blog-history.css">
  <link rel="stylesheet" href="/css/blog-hero-filter.css">
@endpush

@section('content')
  <div class="page container blog-community-mine">
    <header class="page-head">
      <h1 class="page-title">Mis aportes</h1>
      <a class="btn btn-primary" href="{{ route('blog.community.create') }}">+ Nuevo aporte</a>
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
                <th>Estado</th>
                <th>Última actualización</th>
                <th class="text-right">Acciones</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($posts as $post)
                <tr>
                  <td>
                    <a href="{{ route('blog.show', ['post' => $post->slug]) }}" target="_blank" rel="noopener">{{ $post->title }}</a>
                  </td>
                  <td>
                    @if ($post->approved_at)
                      <span class="community-status community-status--published">Publicado</span>
                    @else
                      <span class="community-status community-status--pending">Pendiente</span>
                    @endif
                  </td>
                  <td>
                    {{ $post->updated_at?->timezone(config('app.timezone', 'UTC'))->format('d/m/Y H:i') }}
                  </td>
                  <td class="text-right">
                    <div class="btn-group">
                      @if ($post->approved_at === null)
                        <a class="btn" href="{{ route('blog.community.edit', $post) }}">Editar</a>
                        <form method="post" action="{{ route('blog.community.destroy', $post) }}" onsubmit="return confirm('¿Eliminar el aporte? Esta acción no se puede deshacer.');">
                          @csrf
                          @method('delete')
                          <button class="btn btn-danger" type="submit">Eliminar</button>
                        </form>
                      @else
                        <a class="btn" href="{{ route('blog.show', ['post' => $post->slug]) }}">Ver</a>
                      @endif
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="4">
                    Todavía no tenés aportes. <a href="{{ route('blog.community.create') }}">Creá el primero</a> y compartí tu experiencia.
                  </td>
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
