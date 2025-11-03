@extends('layouts.app')

@php
  $mode = $mode ?? ($post->exists ? 'edit' : 'create');
  $availableTags = $availableTags ?? collect();
  $selectedTagIds = collect(old('tags', $post->tags->pluck('id')->all()))
    ->map(fn ($id) => (int) $id)
    ->all();
@endphp

@section('title', $mode === 'edit' ? 'Editar aporte' : 'Nuevo aporte comunitario')

@push('head')
  <link rel="stylesheet" href="/css/blog-history.css">
  <link rel="stylesheet" href="/css/blog-hero-filter.css">
@endpush

@section('content')
  <div class="page container blog-community-form">
    <header class="page-head">
      <h1 class="page-title">{{ $mode === 'edit' ? 'Editar aporte comunitario' : 'Nuevo aporte comunitario' }}</h1>
      <a class="btn" href="{{ route('blog.community.mine') }}">← Mis aportes</a>
    </header>

    <div class="card">
      <div class="card-body">
        @if ($errors->any())
          <div class="flash flash-error">
            <p><strong>Revisá la información antes de enviarla.</strong></p>
            <ul>
              @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <form method="post" action="{{ $mode === 'edit' ? route('blog.community.update', $post) : route('blog.community.store') }}" class="form blog-community-form-body">
          @csrf
          @if ($mode === 'edit')
            @method('put')
          @endif

          <div class="form-group">
            <label for="title">Título</label>
            <input id="title" name="title" type="text" value="{{ old('title', $post->title) }}" maxlength="255" required>
            <small class="hint">Elegí un título claro y directo que resuma tu aporte.</small>
          </div>

          <div class="form-group">
            <label for="excerpt">Resumen (opcional)</label>
            <textarea id="excerpt" name="excerpt" rows="3" maxlength="500" placeholder="Un breve adelanto de tu publicación.">{{ old('excerpt', $post->excerpt) }}</textarea>
            <small class="hint">Se mostrará en las listas para invitar a otras personas a leerlo.</small>
          </div>

          <div class="form-group">
            <label for="content">Contenido</label>
            <textarea id="content" name="content" rows="12" required>{{ old('content', $post->content) }}</textarea>
            <small class="hint">Podés escribir en texto plano o pegar formato básico desde un editor. Aceptamos títulos, negritas, listas y enlaces.</small>
          </div>

          <div class="form-group">
            <label>Etiquetas (máximo 3)</label>
            @if ($availableTags->isEmpty())
              <p class="hint">Todavía no hay etiquetas creadas. Podés sugerir nuevas más abajo.</p>
            @else
              <div class="blog-community-tags">
                @foreach ($availableTags as $tag)
                  <label class="blog-community-tag-option">
                    <input type="checkbox" name="tags[]" value="{{ $tag['id'] }}" {{ in_array($tag['id'], $selectedTagIds, true) ? 'checked' : '' }}>
                    <span>#{{ $tag['name'] }}</span>
                  </label>
                @endforeach
              </div>
            @endif
            <small class="hint">Las etiquetas ayudan a encontrar el aporte. Elegí las que mejor representen el tema.</small>
          </div>

          <div class="form-group">
            <label for="new_tags">¿Querés proponer nuevas etiquetas?</label>
            <input id="new_tags" type="text" name="new_tags" value="{{ old('new_tags') }}" placeholder="Separalas con comas, por ejemplo: Estrategia, Mesa abierta">
            <small class="hint">Podés sugerir nuevas etiquetas; el equipo las revisará al aprobar el aporte.</small>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn-primary">{{ $mode === 'edit' ? 'Guardar cambios' : 'Enviar para revisión' }}</button>
            <a class="btn" href="{{ route('blog.community.mine') }}">Cancelar</a>
          </div>
        </form>
      </div>
    </div>
  </div>
@endsection
