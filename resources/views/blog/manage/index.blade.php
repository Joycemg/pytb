@extends('layouts.app')

@section('title', 'Administrar blog')

@section('content')
  @php use Illuminate\Support\Str; @endphp
  <div class="page container blog-manage">
    <header class="page-head">
      <h1 class="page-title">Administrar blog</h1>
      <a class="btn btn-primary" href="{{ route('blog.create') }}">+ Nueva entrada</a>
    </header>

    @if (($pendingCount ?? 0) > 0)
      <div class="flash flash-warn">
        Hay {{ $pendingCount }} {{ Str::plural('aporte', $pendingCount) }} de la comunidad pendiente{{ $pendingCount === 1 ? '' : 's' }} de aprobación.
      </div>
    @endif

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
                <th>Estado</th>
                <th>Estilo</th>
                <th>Publicado</th>
                <th class="text-right">Acciones</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($posts as $post)
                @php
                  $isCommunity = (bool) $post->is_community;
                  $statusClasses = $isCommunity
                    ? ($post->approved_at ? 'community-status community-status--published' : 'community-status community-status--pending')
                    : ($post->published_at ? 'community-status community-status--published' : 'community-status community-status--pending');
                  $statusLabel = $isCommunity
                    ? ($post->approved_at ? 'Comunidad · Publicado' : 'Comunidad · Pendiente')
                    : ($post->published_at ? 'Equipo · Publicado' : 'Equipo · Borrador');
                @endphp
                <tr>
                  <td>
                    <a href="{{ route('blog.show', ['post' => $post->slug]) }}" target="_blank" rel="noopener">{{ $post->title }}</a>
                  </td>
                  <td>{{ $post->author->name ?? '—' }}</td>
                  <td>
                    <span class="{{ $statusClasses }}">{{ $statusLabel }}</span>
                  </td>
                  <td>
                    @php
                      $theme = $post->theme ?? config('blog.default_theme', 'classic');
                      $themes = (array) config('blog.themes', []);
                      $label = $themes[$theme]['label'] ?? ucfirst($theme);
                      $accent = $post->accent_color ?? ($themes[$theme]['accent'] ?? config('blog.default_accent'));
                    @endphp
                    <span class="blog-manage-theme" style="--accent-color: {{ $accent }}">{{ $label }}</span>
                  </td>
                  <td>
                    @if ($post->published_at)
                      {{ $post->published_at->timezone(config('app.timezone', 'UTC'))->format('d/m/Y H:i') }}
                    @else
                      <span class="community-status community-status--pending">Borrador</span>
                    @endif
                  </td>
                  <td class="text-right">
                    <div class="btn-group">
                      <a class="btn" href="{{ route('blog.edit', $post) }}">Editar</a>
                      @if ($isCommunity && $post->approved_at === null)
                        <form method="post" action="{{ route('blog.approve', $post) }}">
                          @csrf
                          <button class="btn btn-primary" type="submit">Aprobar</button>
                        </form>
                      @endif
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
